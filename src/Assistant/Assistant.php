<?php

namespace Hibira\FilamentSupport\Assistant;

use Hibira\FilamentSupport\Models\Conversation;
use Hibira\FilamentSupport\Models\Message;
use Hibira\FilamentSupport\Models\Setting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Asks the customer's AI provider for an answer to a visitor.
 */
class Assistant
{
    /** What the model writes when a person should take over. */
    public const HANDOFF = '[HANDOFF]';

    /**
     * The answer to the conversation so far, or null when the model asks
     * for a person.
     *
     * @throws AssistantException
     */
    public function answer(Setting $settings, Conversation $conversation): ?string
    {
        $text = $this->complete($settings, $this->system($settings), $this->history($conversation));

        if ($text === '' || str_contains($text, self::HANDOFF)) {
            return null;
        }

        return mb_substr($text, 0, (int) config('hibira-support.message_characters', 2000));
    }

    /**
     * Sends one question, for the settings page's test button.
     *
     * @throws AssistantException
     */
    public function test(Setting $settings, string $question): string
    {
        return $this->complete($settings, $this->system($settings), [['role' => 'user', 'content' => $question]]);
    }

    public function system(Setting $settings): string
    {
        $app = config('app.name', 'our company');
        $knowledge = mb_substr(trim((string) $settings->knowledge), 0, (int) config('hibira-support.knowledge_characters', 60000));
        $instructions = trim((string) $settings->instructions);

        return implode("\n\n", array_filter([
            "You are the customer support assistant on the website of {$app}. You chat with website visitors.",
            'Answer only questions about '.$app.', using the knowledge below. Reply in the language the visitor writes in. Keep answers short and friendly, at most about 120 words, in plain text without Markdown headings.',
            'Never invent facts, prices, links or policies that are not in the knowledge. Never promise refunds, discounts, credits or changes to an account, and never ask for passwords or card numbers.',
            'Reply with exactly '.self::HANDOFF.' and nothing else when: the knowledge does not answer the question, the visitor asks for a person, or the topic is a refund, a payment problem, a legal question, a security issue or a complaint. A person from the team will then take over.',
            $instructions !== '' ? "Instructions from the team:\n{$instructions}" : null,
            $knowledge !== '' ? "Knowledge:\n{$knowledge}" : null,
        ]));
    }

    /**
     * The conversation as alternating user and assistant turns, newest last,
     * cut to the configured length.
     *
     * @return list<array{role: string, content: string}>
     */
    public function history(Conversation $conversation): array
    {
        $budget = (int) config('hibira-support.history_characters', 16000);
        $turns = [];

        $messages = $conversation->messages()->where('sender', '!=', Message::SYSTEM)->latest('id')->limit(40)->get();

        foreach ($messages as $message) {
            $budget -= mb_strlen($message->body);

            if ($budget < 0 && $turns !== []) {
                break;
            }

            $role = $message->sender === Message::VISITOR ? 'user' : 'assistant';
            $last = $turns[0] ?? null;

            // Providers want alternating turns, so consecutive messages from one side are joined.
            if ($last !== null && $last['role'] === $role) {
                $turns[0]['content'] = $message->body."\n\n".$last['content'];
            } else {
                array_unshift($turns, ['role' => $role, 'content' => $message->body]);
            }
        }

        // Anthropic wants the first turn to be the user's.
        while ($turns !== [] && $turns[0]['role'] !== 'user') {
            array_shift($turns);
        }

        return $turns;
    }

    /**
     * @param  list<array{role: string, content: string}>  $turns
     *
     * @throws AssistantException
     */
    protected function complete(Setting $settings, string $system, array $turns): string
    {
        $url = Providers::url($settings->provider, $settings->base_url);

        if ($url === null || blank($settings->api_key) || blank($settings->model)) {
            throw new AssistantException('The AI provider is not set up.');
        }

        try {
            if (Providers::api($settings->provider) === 'anthropic') {
                $response = Http::timeout(45)
                    ->withHeaders(['x-api-key' => $settings->api_key, 'anthropic-version' => '2023-06-01'])
                    ->post($url.'/messages', [
                        'model' => $settings->model,
                        'system' => $system,
                        'messages' => $turns,
                        'max_tokens' => 700,
                    ]);

                $this->check($response);

                return trim(collect($response->json('content', []))->where('type', 'text')->pluck('text')->implode(''));
            }

            $response = Http::timeout(45)
                ->withToken($settings->api_key)
                ->post($url.'/chat/completions', [
                    'model' => $settings->model,
                    'messages' => [['role' => 'system', 'content' => $system], ...$turns],
                    // OpenAI's reasoning models only take max_completion_tokens; other services know max_tokens.
                    ...($settings->provider === 'openai' ? ['max_completion_tokens' => 2000] : ['max_tokens' => 700]),
                ]);

            $this->check($response);

            return trim((string) $response->json('choices.0.message.content'));
        } catch (AssistantException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new AssistantException($e->getMessage(), 0, $e);
        }
    }

    protected function check(Response $response): void
    {
        if ($response->failed()) {
            $detail = $response->json('error.message') ?? $response->json('error') ?? $response->body();

            throw new AssistantException('HTTP '.$response->status().': '.mb_substr(is_string($detail) ? $detail : json_encode($detail), 0, 300));
        }
    }
}
