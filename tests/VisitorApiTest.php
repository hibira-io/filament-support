<?php

namespace Hibira\FilamentSupport\Tests;

use Hibira\FilamentSupport\Models\Conversation;
use Hibira\FilamentSupport\Models\Message;
use Hibira\FilamentSupport\Models\Setting;

class VisitorApiTest extends TestCase
{
    public function test_serves_the_widget_script(): void
    {
        $response = $this->get('/hibira-support/widget.js')->assertOk();

        $this->assertStringContainsString('javascript', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('private-hibira-support.c.', $response->getContent());
    }

    public function test_config_has_the_public_key_and_the_visitors_language(): void
    {
        Setting::query()->create(['title' => 'Acme help', 'color' => '#112233']);

        $this->getJson('/hibira-support/config?locale=zh-TW')
            ->assertOk()
            ->assertJson([
                'key' => 'pk_test',
                'host' => 'ws.hibira.io',
                'title' => 'Acme help',
                'color' => '#112233',
                'locale' => 'zh_TW',
                'ai' => false,
            ])
            ->assertJsonPath('strings.send', '傳送')
            ->assertJsonMissingPath('secret');
    }

    public function test_never_exposes_the_api_key(): void
    {
        $this->aiSettings();

        $response = $this->getJson('/hibira-support/config')->assertOk()->assertJsonPath('ai', true);

        $this->assertStringNotContainsString('sk-test-1234', $response->getContent());
        $this->assertNotSame('sk-test-1234', Setting::query()->toBase()->value('api_key'));
    }

    public function test_starts_a_conversation_with_the_first_message(): void
    {
        [$id, $token] = $this->startConversation('Where is my order?');

        $conversation = Conversation::findOrFail($id);
        $this->assertTrue($conversation->checkToken($token));
        $this->assertSame('zh_TW', $conversation->locale);
        $this->assertSame(1, $conversation->unread);
        $this->assertSame('Where is my order?', $conversation->messages()->sole()->body);

        $inbox = $this->realtime->on('private-hibira-support.admin.inbox', 'conversation');
        $this->assertSame($id, $inbox[0]['data']['id']);
        $this->assertSame('visitor', $inbox[0]['data']['reason']);
    }

    public function test_messages_need_the_conversation_token(): void
    {
        [$id, $token] = $this->startConversation();

        $this->getJson("/hibira-support/conversations/{$id}")->assertNotFound();
        $this->getJson("/hibira-support/conversations/{$id}", ['X-Hibira-Support-Token' => 'wrong'])->assertNotFound();
        $this->postJson("/hibira-support/conversations/{$id}/messages", ['body' => 'hi'])->assertNotFound();

        $this->postJson("/hibira-support/conversations/{$id}/messages", ['body' => 'Second'], ['X-Hibira-Support-Token' => $token])
            ->assertCreated()
            ->assertJsonPath('body', 'Second');

        $first = Message::query()->orderBy('id')->value('id');

        $this->getJson("/hibira-support/conversations/{$id}?after={$first}", ['X-Hibira-Support-Token' => $token])
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.body', 'Second')
            ->assertJsonPath('channel', "private-hibira-support.c.{$id}");
    }

    public function test_signs_only_the_visitors_own_channel(): void
    {
        [$id, $token] = $this->startConversation();
        [$other] = $this->startConversation();
        $headers = ['X-Hibira-Support-Token' => $token];

        $this->postJson("/hibira-support/conversations/{$id}/auth", ['socket_id' => '1.2', 'channel_name' => "private-hibira-support.c.{$id}"], $headers)
            ->assertOk()
            ->assertJsonPath('auth', "pk_test:signed:private-hibira-support.c.{$id}:1.2");

        $this->postJson("/hibira-support/conversations/{$id}/auth", ['socket_id' => '1.2', 'channel_name' => "private-hibira-support.c.{$other}"], $headers)
            ->assertForbidden();
        $this->postJson("/hibira-support/conversations/{$id}/auth", ['socket_id' => '1.2', 'channel_name' => 'private-hibira-support.admin.inbox'], $headers)
            ->assertForbidden();
    }

    public function test_allows_only_listed_origins(): void
    {
        $this->getJson('/hibira-support/config', ['Origin' => 'https://www.example.com'])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://www.example.com');

        $this->getJson('/hibira-support/config', ['Origin' => 'https://shop.example'])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://shop.example');

        $this->getJson('/hibira-support/config', ['Origin' => 'https://evil.example'])->assertForbidden();

        $this->call('OPTIONS', '/hibira-support/conversations', server: ['HTTP_ORIGIN' => 'https://www.example.com'])
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Headers', 'Content-Type, X-Hibira-Support-Token');
    }

    public function test_limits_new_conversations_per_visitor(): void
    {
        config(['hibira-support.limits.conversations_per_ip_per_hour' => 2]);

        $this->startConversation();
        $this->startConversation();

        $this->postJson('/hibira-support/conversations', ['body' => 'again', 'locale' => 'ja'])
            ->assertStatus(429)
            ->assertJsonPath('message', '送信が早すぎます。少しお待ちください。');
    }

    public function test_rejects_long_and_empty_messages(): void
    {
        $this->postJson('/hibira-support/conversations', ['body' => ''])->assertUnprocessable();
        $this->postJson('/hibira-support/conversations', ['body' => str_repeat('a', 2001)])->assertUnprocessable();
    }

    public function test_reopens_a_closed_conversation_when_the_visitor_writes(): void
    {
        [$id, $token] = $this->startConversation();
        Conversation::whereKey($id)->update(['status' => Conversation::CLOSED]);

        $this->postJson("/hibira-support/conversations/{$id}/messages", ['body' => 'One more thing'], ['X-Hibira-Support-Token' => $token])->assertCreated();

        $this->assertSame(Conversation::OPEN, Conversation::find($id)->status);
    }
}
