<?php

namespace Hibira\FilamentSupport\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $conversation_id
 * @property string $sender visitor, agent, assistant or system
 * @property string|null $user_id the panel user who wrote an agent message
 * @property string|null $author
 * @property string $body
 * @property \Illuminate\Support\Carbon $created_at
 */
class Message extends Model
{
    public const VISITOR = 'visitor';

    public const AGENT = 'agent';

    public const ASSISTANT = 'assistant';

    /** A note such as "passed to the team", shown to both sides. */
    public const SYSTEM = 'system';

    protected $table = 'hibira_support_messages';

    protected $guarded = [];

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return array<string, mixed> what the visitor's widget and the inbox draw */
    public function present(): array
    {
        return [
            'id' => $this->id,
            'sender' => $this->sender,
            'author' => $this->author,
            'body' => $this->body,
            'at' => $this->created_at?->toIso8601String(),
        ];
    }
}
