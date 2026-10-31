<?php

namespace Hibira\FilamentSupport\Tests;

use Filament\Facades\Filament;
use Hibira\FilamentSupport\Models\Conversation;
use Hibira\FilamentSupport\Models\Message;
use Hibira\FilamentSupport\Models\Setting;
use Hibira\FilamentSupport\Pages\Inbox;
use Hibira\FilamentSupport\Pages\SupportSettings;
use Hibira\FilamentSupport\Tests\Fixtures\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

class PanelTest extends TestCase
{
    protected User $owner;

    protected User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::query()->create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'x']);
        $this->agent = User::query()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'x']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_inbox_page_renders_with_its_live_channel(): void
    {
        $this->startConversation('Hello there');

        $this->actingAs($this->agent)
            ->get('/admin/support')
            ->assertOk()
            ->assertSee('hibira-support.admin.inbox', false)
            ->assertSee('Hello there');
    }

    public function test_reply_takes_the_conversation_from_the_ai(): void
    {
        $this->aiSettings(['ai_mode' => Setting::DRAFT]);
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'Draft']]]])]);
        [$id] = $this->startConversation('Hi');

        $this->actingAs($this->agent);
        $page = Livewire::test(Inbox::class);

        $opened = $page->call('openConversation', $id)->effects['returns'][0];
        $this->assertSame('Draft', $opened['conversation']['ai_draft']);
        $this->assertSame(0, Conversation::find($id)->unread);

        $sent = $page->call('reply', $id, "  Hello, Ada here  \n")->effects['returns'][0];
        $this->assertSame('Hello, Ada here', $sent['body']);
        $this->assertSame('Ada', $sent['author']);

        $conversation = Conversation::find($id);
        $this->assertSame(Conversation::HUMAN, $conversation->handled_by);
        $this->assertSame((string) $this->agent->id, $conversation->assigned_to);
        $this->assertNull($conversation->ai_draft);

        $toVisitor = $this->realtime->on("private-hibira-support.c.{$id}", 'message');
        $this->assertSame('Hello, Ada here', end($toVisitor)['data']['body']);

        $mine = $page->call('loadConversations', 'mine')->effects['returns'][0];
        $this->assertSame([$id], array_column($mine, 'id'));
    }

    public function test_hand_back_and_close(): void
    {
        [$id] = $this->startConversation('Hi');
        Conversation::whereKey($id)->update(['handled_by' => Conversation::HUMAN]);

        $this->actingAs($this->agent);
        $page = Livewire::test(Inbox::class);

        $page->call('handBack', $id);
        $this->assertSame(Conversation::ASSISTANT, Conversation::find($id)->handled_by);

        $page->call('close', $id);
        $this->assertSame(Conversation::CLOSED, Conversation::find($id)->status);
        $this->assertNotEmpty($this->realtime->on("private-hibira-support.c.{$id}", 'closed'));
        $this->assertSame([], $page->call('loadConversations', 'open')->effects['returns'][0]);
    }

    public function test_navigation_badge_counts_unread_conversations(): void
    {
        $this->assertNull(Inbox::getNavigationBadge());

        $this->startConversation('One');
        $this->startConversation('Two');

        $this->assertSame('2', Inbox::getNavigationBadge());

        $this->aiSettings();
        $this->assertNull(Inbox::getNavigationBadge());

        Conversation::query()->limit(1)->update(['handled_by' => Conversation::HUMAN]);
        $this->assertSame('1', Inbox::getNavigationBadge());
    }

    public function test_settings_are_limited_to_who_may_manage_them(): void
    {
        $this->actingAs($this->agent)->get('/admin/support/settings')->assertForbidden();
        $this->actingAs($this->owner)->get('/admin/support/settings')->assertOk()->assertSee('hibira-support/widget.js', false);
    }

    public function test_saves_the_key_encrypted_and_keeps_it_when_left_blank(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(SupportSettings::class)
            ->set('ai_mode', Setting::AUTO)
            ->set('provider', 'anthropic')
            ->set('model', 'claude-haiku-5-5')
            ->set('api_key', 'sk-ant-secret-9876')
            ->set('knowledge', 'We open at 10.')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('api_key', '');

        $this->assertSame('sk-ant-secret-9876', Setting::current()->api_key);
        $this->assertStringNotContainsString('9876', (string) Setting::query()->toBase()->value('api_key'));

        Livewire::test(SupportSettings::class)
            ->assertSee('••••9876')
            ->set('knowledge', 'We open at 11.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('sk-ant-secret-9876', Setting::current()->api_key);
        $this->assertSame('We open at 11.', Setting::current()->knowledge);
    }

    public function test_ai_needs_a_key_and_a_model(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(SupportSettings::class)
            ->set('ai_mode', Setting::AUTO)
            ->set('model', 'gpt-5-mini')
            ->call('save')
            ->assertHasErrors(['api_key']);

        Livewire::test(SupportSettings::class)
            ->set('ai_mode', Setting::AUTO)
            ->set('api_key', 'sk-1')
            ->call('save')
            ->assertHasErrors(['model']);

        Livewire::test(SupportSettings::class)
            ->set('provider', 'custom')
            ->call('save')
            ->assertHasErrors(['base_url']);
    }

    public function test_try_a_question_uses_the_unsaved_form(): void
    {
        $this->actingAs($this->owner);
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'We open at 10.']]]])]);

        Livewire::test(SupportSettings::class)
            ->set('model', 'gpt-5-mini')
            ->set('api_key', 'sk-try')
            ->set('knowledge', 'Opening hours: 10 to 19.')
            ->set('question', 'When do you open?')
            ->call('test')
            ->assertSet('answer', 'We open at 10.')
            ->assertSet('error', null);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer sk-try')
            && str_contains($request['messages'][0]['content'], 'Opening hours: 10 to 19.'));
        $this->assertNull(Setting::query()->first());
    }

    public function test_forgetting_the_key_turns_ai_off(): void
    {
        $this->aiSettings();
        $this->actingAs($this->owner);

        Livewire::test(SupportSettings::class)->call('forgetKey')->assertSet('ai_mode', Setting::OFF);

        $this->assertNull(Setting::current()->api_key);
        $this->assertFalse(Setting::current()->aiEnabled());
    }

    public function test_only_panel_users_may_listen_to_the_inbox(): void
    {
        $authorize = Broadcast::driver()->getChannels()->get('hibira-support.admin.inbox');

        $this->assertIsCallable($authorize);
        $this->assertTrue($authorize($this->agent));
    }

    public function test_panel_pages_show_toasts_for_visitors_waiting_on_the_team(): void
    {
        $this->actingAs($this->agent)
            ->get('/admin')
            ->assertOk()
            ->assertSee('FilamentNotification', false)
            ->assertSee('hibira-support.admin.inbox', false);
    }

    public function test_agent_messages_are_stored_as_the_agent(): void
    {
        [$id] = $this->startConversation('Hi');
        $this->actingAs($this->agent);

        Livewire::test(Inbox::class)->call('reply', $id, 'Hi!');

        $message = Message::query()->where('sender', Message::AGENT)->sole();
        $this->assertSame((string) $this->agent->id, $message->user_id);
    }
}
