<?php

namespace Hibira\FilamentSupport\Jobs;

use Hibira\FilamentSupport\Assistant\Assistant;
use Hibira\FilamentSupport\Assistant\AssistantException;
use Hibira\FilamentSupport\Desk;
use Hibira\FilamentSupport\Models\Conversation;
use Hibira\FilamentSupport\Models\Message;
use Hibira\FilamentSupport\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Answers a visitor's message with the customer's AI, or writes a draft for
 * the team, within the abuse limits.
 */
class AnswerVisitor implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public string $conversationId, public int $messageId) {}

    public static function schedule(Conversation $conversation, Message $message): void
    {
        $job = new static($conversation->id, $message->id);

        // Without a queue worker the answer would hold up the visitor's
        // request, so it runs once the response is sent.
        if (config('queue.default') === 'sync') {
            dispatch($job)->afterResponse();
        } else {
            dispatch($job)->delay(now()->addSeconds((int) config('hibira-support.answer_delay', 2)));
        }
    }

    public function handle(Desk $desk, Assistant $assistant): void
    {
        $conversation = Conversation::find($this->conversationId);
        $settings = Setting::current();

        if ($conversation === null || ! $conversation->isHandledByAssistant() || ! $settings->aiEnabled()) {
            return;
        }

        // A newer message has its own job, which answers them together.
        if ($conversation->messages()->where('sender', Message::VISITOR)->max('id') !== $this->messageId) {
            return;
        }

        if (! $this->withinLimits($conversation, $settings)) {
            $desk->handOff($conversation);

            return;
        }

        $auto = $settings->ai_mode === Setting::AUTO;

        if ($auto) {
            $desk->typing($conversation, 'assistant');
        }

        try {
            $answer = $assistant->answer($settings, $conversation);
        } catch (AssistantException $e) {
            report($e);
            $answer = null;
        }

        $conversation->refresh();

        // A person may have answered while the AI was thinking.
        if (! $conversation->isHandledByAssistant()) {
            return;
        }

        if ($answer === null) {
            if ($auto) {
                $desk->handOff($conversation);
            }

            return;
        }

        $auto ? $desk->assistantMessage($conversation, $answer) : $desk->draft($conversation, $answer);
    }

    protected function withinLimits(Conversation $conversation, Setting $settings): bool
    {
        $limits = config('hibira-support.limits');

        if ($conversation->ai_replies >= $limits['ai_replies_per_conversation']) {
            return false;
        }

        $today = Message::query()->where('sender', Message::ASSISTANT)->where('created_at', '>=', now()->startOfDay())->count();

        if ($settings->daily_limit > 0 && $today >= $settings->daily_limit) {
            return false;
        }

        $ip = $conversation->ip_hash ?: $conversation->id;

        if (RateLimiter::tooManyAttempts("hibira-support:ai:10m:{$ip}", $limits['ai_replies_per_ip_per_10_minutes'])
            || RateLimiter::tooManyAttempts("hibira-support:ai:day:{$ip}", $limits['ai_replies_per_ip_per_day'])) {
            return false;
        }

        RateLimiter::hit("hibira-support:ai:10m:{$ip}", 600);
        RateLimiter::hit("hibira-support:ai:day:{$ip}", 86400);

        return true;
    }
}
