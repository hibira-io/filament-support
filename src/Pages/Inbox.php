<?php

namespace Hibira\FilamentSupport\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Page;
use Hibira\FilamentSupport\Desk;
use Hibira\FilamentSupport\HibiraSupportPlugin;
use Hibira\FilamentSupport\Models\Conversation;
use Hibira\FilamentSupport\Models\Message;
use Hibira\FilamentSupport\Models\Setting;
use Illuminate\Contracts\Auth\Authenticatable;
use Livewire\Attributes\Renderless;

/**
 * Website visitors' conversations, live. The page renders once; after that
 * the browser fetches with renderless calls whenever Hibira says something
 * changed.
 */
class Inbox extends Page
{
    protected static ?string $slug = 'support';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-lifebuoy';
    }

    public static function getNavigationLabel(): string
    {
        return __('hibira-support::support.inbox');
    }

    public static function getNavigationGroup(): ?string
    {
        return HibiraSupportPlugin::get()->getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return HibiraSupportPlugin::get()->getNavigationSort();
    }

    public static function getNavigationBadge(): ?string
    {
        $settings = Setting::current();

        // While the AI answers on its own, only conversations waiting on a person count.
        $count = Conversation::query()
            ->where('status', Conversation::OPEN)
            ->where('unread', '>', 0)
            ->when($settings->aiEnabled() && $settings->ai_mode === Setting::AUTO, fn ($q) => $q->where('handled_by', Conversation::HUMAN))
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function canAccess(): bool
    {
        return HibiraSupportPlugin::get()->canAnswer(Filament::auth()->user());
    }

    public function getTitle(): string
    {
        return __('hibira-support::support.inbox');
    }

    public function getView(): string
    {
        return 'hibira-support::inbox';
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $settings = Setting::current();

        return [
            'conversations' => $this->loadConversations('open'),
            'channel' => HibiraSupportPlugin::inboxChannel(Filament::getCurrentPanel()->getId()),
            'aiMode' => $settings->aiEnabled() ? $settings->ai_mode : Setting::OFF,
            'settingsUrl' => SupportSettings::canAccess() ? SupportSettings::getUrl() : null,
        ];
    }

    /** @return list<array<string, mixed>> */
    #[Renderless]
    public function loadConversations(string $filter = 'open'): array
    {
        return Conversation::query()
            ->when($filter === 'open', fn ($q) => $q->where('status', Conversation::OPEN))
            ->when($filter === 'closed', fn ($q) => $q->where('status', Conversation::CLOSED))
            ->when($filter === 'mine', fn ($q) => $q->where('status', Conversation::OPEN)->where('assigned_to', (string) $this->me()->getAuthIdentifier()))
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get()
            ->map(fn (Conversation $c) => $this->present($c))
            ->all();
    }

    /** @return array<string, mixed>|null */
    #[Renderless]
    public function openConversation(string $id): ?array
    {
        $conversation = Conversation::find($id);

        if ($conversation === null) {
            return null;
        }

        if ($conversation->unread > 0) {
            $conversation->forceFill(['unread' => 0])->save();
        }

        return [
            'conversation' => $this->present($conversation),
            'messages' => $conversation->messages()->orderByDesc('id')->limit(200)->get()->reverse()->values()->map(fn (Message $m) => $m->present())->all(),
        ];
    }

    /** @return array<string, mixed>|null */
    #[Renderless]
    public function reply(string $id, string $body): ?array
    {
        $body = mb_substr(trim($body), 0, (int) config('hibira-support.message_characters', 2000));
        $conversation = Conversation::find($id);

        if ($body === '' || $conversation === null) {
            return null;
        }

        $me = $this->me();

        return app(Desk::class)->agentMessage($conversation, $me, Filament::getUserName($me), $body)->present();
    }

    #[Renderless]
    public function handBack(string $id): void
    {
        if ($conversation = Conversation::find($id)) {
            app(Desk::class)->handBack($conversation);
        }
    }

    #[Renderless]
    public function takeOver(string $id): void
    {
        if ($conversation = Conversation::find($id)) {
            $conversation->forceFill(['handled_by' => Conversation::HUMAN, 'assigned_to' => (string) $this->me()->getAuthIdentifier()])->save();
        }
    }

    #[Renderless]
    public function close(string $id): void
    {
        if ($conversation = Conversation::find($id)) {
            app(Desk::class)->close($conversation);
        }
    }

    #[Renderless]
    public function typing(string $id): void
    {
        if ($conversation = Conversation::find($id)) {
            app(Desk::class)->typing($conversation, 'agent');
        }
    }

    /** @return array<string, mixed> */
    protected function present(Conversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'name' => $conversation->displayName(),
            'email' => $conversation->visitor_email,
            'page_url' => $conversation->page_url,
            'locale' => $conversation->locale,
            'status' => $conversation->status,
            'handled_by' => $conversation->handled_by,
            'mine' => $conversation->assigned_to === (string) $this->me()->getAuthIdentifier(),
            'unread' => $conversation->unread,
            'ai_draft' => $conversation->ai_draft,
            'preview' => $conversation->last_message,
            'at' => $conversation->last_message_at?->toIso8601String(),
        ];
    }

    protected function me(): Authenticatable
    {
        return Filament::auth()->user();
    }
}
