<?php

namespace Hibira\FilamentSupport\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A website visitor's chat with your team.
 *
 * @property string $id
 * @property string $token_hash
 * @property string|null $visitor_name
 * @property string|null $visitor_email
 * @property string|null $locale
 * @property string|null $page_url
 * @property string|null $ip_hash
 * @property string $status open or closed
 * @property string $handled_by assistant or human
 * @property string|null $assigned_to
 * @property int $unread messages your team has not read
 * @property int $ai_replies
 * @property string|null $ai_draft
 * @property string|null $last_message
 * @property \Illuminate\Support\Carbon|null $last_message_at
 */
class Conversation extends Model
{
    use HasUlids;

    public const OPEN = 'open';

    public const CLOSED = 'closed';

    public const ASSISTANT = 'assistant';

    public const HUMAN = 'human';

    protected $table = 'hibira_support_conversations';

    protected $guarded = [];

    protected $attributes = [
        'status' => self::OPEN,
        'handled_by' => self::ASSISTANT,
        'unread' => 0,
        'ai_replies' => 0,
    ];

    protected $hidden = ['token_hash', 'ip_hash'];

    protected $casts = [
        'unread' => 'integer',
        'ai_replies' => 'integer',
        'last_message_at' => 'datetime',
    ];

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Starts a conversation and returns it with the visitor's token, which
     * is shown once and stored only as a hash.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: static, 1: string}
     */
    public static function start(array $attributes): array
    {
        $token = Str::random(40);

        $conversation = static::create($attributes + ['token_hash' => hash('sha256', $token)]);

        return [$conversation, $token];
    }

    public function checkToken(?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals($this->token_hash, hash('sha256', $token));
    }

    /** The private channel the visitor's browser listens on, with its prefix. */
    public function channel(): string
    {
        return 'private-hibira-support.c.'.$this->id;
    }

    public function isHandledByAssistant(): bool
    {
        return $this->handled_by === self::ASSISTANT;
    }

    public function displayName(): string
    {
        return $this->visitor_name ?: __('hibira-support::support.visitor', ['id' => strtoupper(substr($this->id, -4))]);
    }
}
