<?php

namespace Hibira\FilamentSupport;

use Hibira\FilamentSupport\Jobs\AnswerVisitor;
use Hibira\FilamentSupport\Models\Conversation;
use Hibira\FilamentSupport\Models\Message;
use Hibira\FilamentSupport\Models\Setting;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * Everything that changes a conversation: stores it first, then tells the
 * visitor's widget and the team's inbox over Hibira.
 */
class Desk
{
    /** Words that mean "let me talk to a person", in the widget's languages. */
    public const HUMAN_WORDS = [
        'human', 'real person', 'agent', 'operator', 'talk to someone', 'speak to someone', 'representative',
        '人工', '真人', '客服人員', '客服人员', '转人工', '轉人工',
        '担当者', 'オペレーター', '人間', 'スタッフ',
        '상담원', '사람', '직원',
        'เจ้าหน้าที่', 'คนจริง',
        'nhân viên', 'người thật', 'tư vấn viên',
    ];

    public function __construct(protected Realtime $realtime) {}

    public function visitorMessage(Conversation $conversation, string $body): Message
    {
        $message = DB::transaction(function () use ($conversation, $body) {
            $message = $conversation->messages()->create(['sender' => Message::VISITOR, 'body' => $body]);

            $conversation->forceFill([
                'status' => Conversation::OPEN,
                'unread' => $conversation->unread + 1,
                'last_message' => mb_substr($body, 0, 200),
                'last_message_at' => $message->created_at,
            ])->save();

            return $message;
        });

        $this->toInbox($conversation, 'visitor');

        if ($conversation->isHandledByAssistant() && $this->asksForHuman($body)) {
            $this->handOff($conversation);
        } elseif ($conversation->isHandledByAssistant() && Setting::current()->aiEnabled()) {
            AnswerVisitor::schedule($conversation, $message);
        }

        return $message;
    }

    public function agentMessage(Conversation $conversation, Authenticatable $agent, string $name, string $body): Message
    {
        $message = DB::transaction(function () use ($conversation, $agent, $name, $body) {
            $message = $conversation->messages()->create([
                'sender' => Message::AGENT,
                'user_id' => (string) $agent->getAuthIdentifier(),
                'author' => $name,
                'body' => $body,
            ]);

            // Once a person answers, the AI stays out of this conversation.
            $conversation->forceFill([
                'status' => Conversation::OPEN,
                'handled_by' => Conversation::HUMAN,
                'assigned_to' => (string) $agent->getAuthIdentifier(),
                'ai_draft' => null,
                'unread' => 0,
                'last_message' => mb_substr($body, 0, 200),
                'last_message_at' => $message->created_at,
            ])->save();

            return $message;
        });

        $this->toVisitor($conversation, $message);
        $this->toInbox($conversation, 'agent');

        return $message;
    }

    public function assistantMessage(Conversation $conversation, string $body): Message
    {
        $message = DB::transaction(function () use ($conversation, $body) {
            $message = $conversation->messages()->create(['sender' => Message::ASSISTANT, 'body' => $body]);

            $conversation->forceFill([
                'ai_replies' => $conversation->ai_replies + 1,
                'last_message' => mb_substr($body, 0, 200),
                'last_message_at' => $message->created_at,
            ])->save();

            return $message;
        });

        $this->toVisitor($conversation, $message);
        $this->toInbox($conversation, 'assistant');

        return $message;
    }

    public function draft(Conversation $conversation, string $body): void
    {
        $conversation->forceFill(['ai_draft' => $body])->save();

        $this->toInbox($conversation, 'draft');
    }

    /** Passes the conversation to the team and tells the visitor. */
    public function handOff(Conversation $conversation): void
    {
        if (! $conversation->isHandledByAssistant()) {
            return;
        }

        $conversation->forceFill(['handled_by' => Conversation::HUMAN])->save();

        $message = $conversation->messages()->create([
            'sender' => Message::SYSTEM,
            'body' => 'handoff',
        ]);

        $this->toVisitor($conversation, $message);
        $this->toInbox($conversation, 'handoff');
    }

    public function handBack(Conversation $conversation): void
    {
        $conversation->forceFill(['handled_by' => Conversation::ASSISTANT, 'assigned_to' => null])->save();

        $this->toInbox($conversation, 'updated');
    }

    public function close(Conversation $conversation): void
    {
        $conversation->forceFill(['status' => Conversation::CLOSED, 'unread' => 0])->save();

        $this->realtime->trigger($conversation->channel(), 'closed', []);
        $this->toInbox($conversation, 'updated');
    }

    public function typing(Conversation $conversation, string $by): void
    {
        $this->realtime->trigger($conversation->channel(), 'typing', ['by' => $by]);
    }

    public function asksForHuman(string $body): bool
    {
        $text = mb_strtolower($body);

        foreach (self::HUMAN_WORDS as $word) {
            if (str_contains($text, $word)) {
                return true;
            }
        }

        return false;
    }

    protected function toVisitor(Conversation $conversation, Message $message): void
    {
        $this->realtime->trigger($conversation->channel(), 'message', Realtime::fit($message->present()));
    }

    /** Every panel with the plugin listens on its own inbox channel. */
    protected function toInbox(Conversation $conversation, string $reason): void
    {
        foreach (HibiraSupportPlugin::panels() as $panel) {
            $this->realtime->trigger(HibiraSupportPlugin::inboxChannel($panel, prefixed: true), 'conversation', [
                'id' => $conversation->id,
                'reason' => $reason,
                'handled_by' => $conversation->handled_by,
                'name' => $conversation->displayName(),
                'preview' => mb_substr((string) $conversation->last_message, 0, 120),
            ]);
        }
    }
}
