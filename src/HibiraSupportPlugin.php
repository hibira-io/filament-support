<?php

namespace Hibira\FilamentSupport;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Hibira\Filament\Presence;
use Hibira\FilamentSupport\Pages\Inbox;
use Hibira\FilamentSupport\Pages\SupportSettings;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Broadcast;

/**
 * Adds the support inbox and the AI settings page to a Filament panel.
 *
 * Use it next to HibiraPlugin from hibira-io/filament, which connects the
 * panel to Hibira.
 */
class HibiraSupportPlugin implements Plugin
{
    /** @var array<string, true> panels that have the plugin */
    protected static array $panels = [];

    protected ?string $navigationGroup = null;

    protected ?int $navigationSort = null;

    protected bool $hasToasts = true;

    protected ?Closure $authorizeUsing = null;

    protected ?Closure $authorizeSettingsUsing = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static */
        return filament(app(static::class)->getId());
    }

    public function getId(): string
    {
        return 'hibira-support';
    }

    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    /** Shows a notification anywhere in the panel when a visitor writes. */
    public function toasts(bool $condition = true): static
    {
        $this->hasToasts = $condition;

        return $this;
    }

    /** Who may answer visitors. Everyone who can use the panel, by default. */
    public function authorize(?Closure $callback): static
    {
        $this->authorizeUsing = $callback;

        return $this;
    }

    /** Who may change the AI settings and the API key. Same as authorize(), by default. */
    public function authorizeSettings(?Closure $callback): static
    {
        $this->authorizeSettingsUsing = $callback;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort;
    }

    public function canAnswer(?Authenticatable $user): bool
    {
        return $user !== null && ($this->authorizeUsing === null || (bool) ($this->authorizeUsing)($user));
    }

    public function canManageSettings(?Authenticatable $user): bool
    {
        if (! $this->canAnswer($user)) {
            return false;
        }

        return $this->authorizeSettingsUsing === null || (bool) ($this->authorizeSettingsUsing)($user);
    }

    public function register(Panel $panel): void
    {
        static::$panels[$panel->getId()] = true;

        $panel->pages([Inbox::class, SupportSettings::class]);

        // Authorized on /broadcasting/auth, outside the panel, so registered for every request.
        app()->booted(function () use ($panel) {
            Broadcast::channel(
                static::inboxChannel($panel->getId()),
                fn ($user) => Presence::member($panel->getId(), $user) !== false && $this->canAnswer($user),
                $panel->getAuthGuard() ? ['guards' => [$panel->getAuthGuard()]] : [],
            );
        });
    }

    public function boot(Panel $panel): void
    {
        if (! $this->hasToasts || blank(config('hibira.key'))) {
            return;
        }

        FilamentView::registerRenderHook(PanelsRenderHook::BODY_END, function () use ($panel): string {
            if (! $this->canAnswer(Filament::auth()->user())) {
                return '';
            }

            return view('hibira-support::toasts', [
                'channel' => static::inboxChannel($panel->getId()),
                'url' => Inbox::getUrl(panel: $panel->getId()),
            ])->render();
        });
    }

    /** @return list<string> */
    public static function panels(): array
    {
        return array_keys(static::$panels);
    }

    /** The inbox channel's Laravel name, or with its private- prefix for Hibira. */
    public static function inboxChannel(string $panelId, bool $prefixed = false): string
    {
        return ($prefixed ? 'private-' : '')."hibira-support.{$panelId}.inbox";
    }
}
