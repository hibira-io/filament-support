<?php

namespace Hibira\FilamentSupport\Pages;

use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Hibira\FilamentSupport\Assistant\Assistant;
use Hibira\FilamentSupport\Assistant\AssistantException;
use Hibira\FilamentSupport\Assistant\Providers;
use Hibira\FilamentSupport\HibiraSupportPlugin;
use Hibira\FilamentSupport\Models\Setting;
use Illuminate\Validation\Rule;

/**
 * The AI provider, its key, what it knows, and how the widget looks.
 *
 * Built from Filament's Blade components rather than a form schema, so the
 * same page works on Filament 3, 4 and 5.
 */
class SupportSettings extends Page
{
    protected static ?string $slug = 'support/settings';

    protected static bool $shouldRegisterNavigation = false;

    public string $ai_mode = Setting::OFF;

    public string $provider = 'openai';

    public string $model = '';

    /** Left blank to keep the saved key. */
    public string $api_key = '';

    public string $base_url = '';

    public string $instructions = '';

    public string $knowledge = '';

    public int $daily_limit = 200;

    public string $widget_title = '';

    public string $greeting = '';

    public string $color = '#d97706';

    public string $question = '';

    public ?string $answer = null;

    public ?string $error = null;

    public static function canAccess(): bool
    {
        return HibiraSupportPlugin::get()->canManageSettings(Filament::auth()->user());
    }

    public function getTitle(): string
    {
        return __('hibira-support::settings.title');
    }

    public function getView(): string
    {
        return 'hibira-support::settings';
    }

    public function mount(): void
    {
        $settings = Setting::current();

        $this->ai_mode = $settings->ai_mode;
        $this->provider = $settings->provider;
        $this->model = (string) $settings->model;
        $this->base_url = (string) $settings->base_url;
        $this->instructions = (string) $settings->instructions;
        $this->knowledge = (string) $settings->knowledge;
        $this->daily_limit = $settings->daily_limit;
        $this->widget_title = (string) $settings->title;
        $this->greeting = (string) $settings->greeting;
        $this->color = $settings->color();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $saved = Setting::current();

        return [
            'providers' => Providers::options(),
            'suggestedModel' => Providers::suggestedModel($this->provider),
            'needsUrl' => Providers::url($this->provider, null) === null,
            'keyHint' => filled($saved->api_key) ? '••••'.mb_substr($saved->api_key, -4) : null,
            'inboxUrl' => Inbox::getUrl(),
            'snippet' => '<script src="'.route('hibira-support.widget').'" async></script>',
            'hibiraReady' => filled(config('hibira.key')),
        ];
    }

    public function save(): void
    {
        $data = $this->validate([
            'ai_mode' => ['required', Rule::in([Setting::OFF, Setting::DRAFT, Setting::AUTO])],
            'provider' => ['required', Rule::in(array_keys(Providers::ALL))],
            'model' => ['nullable', 'string', 'max:200', Rule::requiredIf($this->ai_mode !== Setting::OFF)],
            'api_key' => ['nullable', 'string', 'max:500'],
            'base_url' => ['nullable', 'url', 'max:255', Rule::requiredIf($this->provider === 'custom')],
            'instructions' => ['nullable', 'string', 'max:4000'],
            'knowledge' => ['nullable', 'string', 'max:'.config('hibira-support.knowledge_characters', 60000)],
            'daily_limit' => ['required', 'integer', 'min:0', 'max:100000'],
            'widget_title' => ['nullable', 'string', 'max:60'],
            'greeting' => ['nullable', 'string', 'max:500'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $settings = Setting::query()->first() ?? new Setting;

        if ($data['ai_mode'] !== Setting::OFF && blank($data['api_key']) && blank($settings->api_key)) {
            $this->addError('api_key', __('hibira-support::settings.key_required'));

            return;
        }

        $data['title'] = $data['widget_title'];
        unset($data['widget_title']);

        if (blank($data['api_key'])) {
            unset($data['api_key']);
        }

        $settings->fill($data)->save();
        $this->api_key = '';

        Notification::make()->title(__('hibira-support::settings.saved'))->success()->send();
    }

    public function forgetKey(): void
    {
        Setting::query()->update(['api_key' => null, 'ai_mode' => Setting::OFF]);
        $this->ai_mode = Setting::OFF;

        Notification::make()->title(__('hibira-support::settings.key_removed'))->success()->send();
    }

    /** Asks the saved provider one question, with the knowledge and instructions on the page. */
    public function test(Assistant $assistant): void
    {
        $this->answer = null;
        $this->error = null;

        $saved = Setting::current();
        $settings = new Setting([
            'provider' => $this->provider,
            'model' => $this->model,
            'base_url' => $this->base_url ?: null,
            'api_key' => filled($this->api_key) ? $this->api_key : $saved->api_key,
            'instructions' => $this->instructions,
            'knowledge' => $this->knowledge,
        ]);

        try {
            $this->answer = $assistant->test($settings, trim($this->question) ?: __('hibira-support::settings.test_default'));
        } catch (AssistantException $e) {
            $this->error = $e->getMessage();
        }
    }
}
