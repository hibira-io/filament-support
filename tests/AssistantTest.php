<?php

namespace Hibira\FilamentSupport\Tests;

use Hibira\FilamentSupport\Models\Conversation;
use Hibira\FilamentSupport\Models\Message;
use Hibira\FilamentSupport\Models\Setting;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class AssistantTest extends TestCase
{
    protected function fakeOpenAi(string $answer): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => $answer]]]])]);
    }

    public function test_answers_automatically_with_the_customers_key(): void
    {
        $this->aiSettings();
        $this->fakeOpenAi('It takes 2-3 days.');

        [$id] = $this->startConversation('How long is shipping?');

        $answer = Message::query()->where('sender', Message::ASSISTANT)->sole();
        $this->assertSame('It takes 2-3 days.', $answer->body);
        $this->assertSame(1, Conversation::find($id)->ai_replies);

        Http::assertSent(function (Request $request) {
            $messages = $request['messages'];

            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer sk-test-1234')
                && $request['model'] === 'gpt-5-mini'
                && $messages[0]['role'] === 'system'
                && str_contains($messages[0]['content'], 'Shipping takes 2-3 days.')
                && str_contains($messages[0]['content'], 'Acme Shop')
                && end($messages) === ['role' => 'user', 'content' => 'How long is shipping?'];
        });

        $visitor = $this->realtime->on("private-hibira-support.c.{$id}");
        $this->assertSame(['typing', 'message'], array_column($visitor, 'event'));
        $this->assertSame('It takes 2-3 days.', $visitor[1]['data']['body']);
        $this->assertSame('assistant', $visitor[1]['data']['sender']);
    }

    public function test_hands_off_when_the_model_asks_for_a_person(): void
    {
        $this->aiSettings();
        $this->fakeOpenAi('[HANDOFF]');

        [$id] = $this->startConversation('I want a refund');

        $conversation = Conversation::find($id);
        $this->assertSame(Conversation::HUMAN, $conversation->handled_by);
        $this->assertSame(0, $conversation->ai_replies);
        $this->assertSame(['visitor', 'system'], $conversation->messages()->orderBy('id')->pluck('sender')->all());
        $this->assertNotEmpty($this->realtime->on('private-hibira-support.admin.inbox', 'conversation'));
        $this->assertSame('handoff', collect($this->realtime->sent)->last()['data']['reason']);
    }

    public function test_hands_off_without_asking_the_ai_when_the_visitor_wants_a_person(): void
    {
        $this->aiSettings();
        Http::fake();

        [$id] = $this->startConversation('我要找真人客服');

        Http::assertNothingSent();
        $this->assertSame(Conversation::HUMAN, Conversation::find($id)->handled_by);
    }

    public function test_hands_off_when_the_provider_fails(): void
    {
        $this->aiSettings();
        Http::fake(['*' => Http::response(['error' => ['message' => 'Incorrect API key']], 401)]);

        [$id] = $this->startConversation('Hi');

        $this->assertSame(Conversation::HUMAN, Conversation::find($id)->handled_by);
    }

    public function test_stays_quiet_once_a_person_answered(): void
    {
        $this->aiSettings();
        $this->fakeOpenAi('AI answer');

        [$id, $token] = $this->startConversation('Hi');
        Conversation::whereKey($id)->update(['handled_by' => Conversation::HUMAN]);

        $this->postJson("/hibira-support/conversations/{$id}/messages", ['body' => 'Still there?'], ['X-Hibira-Support-Token' => $token])->assertCreated();

        $this->assertSame(1, Message::query()->where('sender', Message::ASSISTANT)->count());
    }

    public function test_draft_mode_suggests_without_answering_the_visitor(): void
    {
        $this->aiSettings(['ai_mode' => Setting::DRAFT]);
        $this->fakeOpenAi('Suggested answer');

        [$id] = $this->startConversation('Do you ship to Japan?');

        $this->assertSame('Suggested answer', Conversation::find($id)->ai_draft);
        $this->assertSame(0, Message::query()->where('sender', Message::ASSISTANT)->count());
        $this->assertSame([], $this->realtime->on("private-hibira-support.c.{$id}"));
        $this->assertSame('draft', collect($this->realtime->sent)->last()['data']['reason']);

        $this->getJson('/hibira-support/config')->assertJsonPath('ai', false);
    }

    public function test_off_mode_never_calls_the_provider(): void
    {
        $this->aiSettings(['ai_mode' => Setting::OFF]);
        Http::fake();

        $this->startConversation('Hi');

        Http::assertNothingSent();
    }

    public function test_hands_off_after_the_per_conversation_limit(): void
    {
        config(['hibira-support.limits.ai_replies_per_conversation' => 2]);
        $this->aiSettings();
        $this->fakeOpenAi('Answer');

        [$id, $token] = $this->startConversation('One');

        foreach (['Two', 'Three'] as $body) {
            $this->postJson("/hibira-support/conversations/{$id}/messages", ['body' => $body], ['X-Hibira-Support-Token' => $token])->assertCreated();
        }

        $this->assertSame(2, Message::query()->where('sender', Message::ASSISTANT)->count());
        $this->assertSame(Conversation::HUMAN, Conversation::find($id)->handled_by);
        Http::assertSentCount(2);
    }

    public function test_hands_off_after_the_daily_limit(): void
    {
        $this->aiSettings(['daily_limit' => 1]);
        $this->fakeOpenAi('Answer');

        $this->startConversation('First visitor');
        [$second] = $this->startConversation('Second visitor');

        Http::assertSentCount(1);
        $this->assertSame(Conversation::HUMAN, Conversation::find($second)->handled_by);
    }

    public function test_hands_off_after_the_per_visitor_limit(): void
    {
        config(['hibira-support.limits.ai_replies_per_ip_per_10_minutes' => 1]);
        $this->aiSettings();
        $this->fakeOpenAi('Answer');

        $this->startConversation('First');
        [$second] = $this->startConversation('Same visitor, new chat');

        Http::assertSentCount(1);
        $this->assertSame(Conversation::HUMAN, Conversation::find($second)->handled_by);
    }

    public function test_anthropic_gets_alternating_turns_starting_with_the_visitor(): void
    {
        $this->aiSettings(['provider' => 'anthropic', 'model' => 'claude-haiku-5-5']);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Hello from Claude']]])]);

        [$id] = $this->startConversation('Hi');
        $conversation = Conversation::find($id);
        $conversation->messages()->create(['sender' => Message::VISITOR, 'body' => 'Second question']);
        $conversation->messages()->create(['sender' => Message::VISITOR, 'body' => 'Third question']);

        $turns = app(\Hibira\FilamentSupport\Assistant\Assistant::class)->history($conversation);

        $this->assertSame([
            ['role' => 'user', 'content' => 'Hi'],
            ['role' => 'assistant', 'content' => 'Hello from Claude'],
            ['role' => 'user', 'content' => "Second question\n\nThird question"],
        ], $turns);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->hasHeader('x-api-key', 'sk-test-1234')
            && is_string($request['system'])
            && $request['messages'] === [['role' => 'user', 'content' => 'Hi']]);
    }

    public function test_openai_compatible_services_use_their_own_url(): void
    {
        $this->aiSettings(['provider' => 'custom', 'base_url' => 'http://ollama.local:11434/v1/', 'model' => 'llama3.1']);
        Http::fake(['ollama.local:11434/*' => Http::response(['choices' => [['message' => ['content' => 'Local answer']]]])]);

        $this->startConversation('Hi');

        Http::assertSent(fn (Request $request) => $request->url() === 'http://ollama.local:11434/v1/chat/completions'
            && $request['max_tokens'] === 700);
        $this->assertSame('Local answer', Message::query()->where('sender', Message::ASSISTANT)->value('body'));
    }
}
