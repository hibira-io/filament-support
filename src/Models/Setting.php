<?php

namespace Hibira\FilamentSupport\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The support desk's settings, one row, edited on the panel's AI settings page.
 *
 * @property string $ai_mode off, draft or auto
 * @property string $provider
 * @property string|null $model
 * @property string|null $api_key encrypted with the app key
 * @property string|null $base_url
 * @property string|null $instructions
 * @property string|null $knowledge
 * @property int $daily_limit
 * @property string|null $title
 * @property string|null $greeting
 * @property string|null $color
 */
class Setting extends Model
{
    public const OFF = 'off';

    /** The AI writes a suggested reply in the inbox; your team sends it. */
    public const DRAFT = 'draft';

    /** The AI answers visitors itself until it hands over. */
    public const AUTO = 'auto';

    protected $table = 'hibira_support_settings';

    protected $guarded = [];

    protected $attributes = [
        'ai_mode' => self::OFF,
        'provider' => 'openai',
        'model' => null,
        'api_key' => null,
        'base_url' => null,
        'instructions' => null,
        'knowledge' => null,
        'daily_limit' => 200,
        'title' => null,
        'greeting' => null,
        'color' => null,
    ];

    protected $hidden = ['api_key'];

    protected $casts = [
        'api_key' => 'encrypted',
        'daily_limit' => 'integer',
    ];

    public static function current(): static
    {
        return static::query()->first() ?? new static;
    }

    public function aiEnabled(): bool
    {
        return $this->ai_mode !== self::OFF && filled($this->api_key) && filled($this->model);
    }

    public function color(): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string) $this->color) ? $this->color : '#d97706';
    }
}
