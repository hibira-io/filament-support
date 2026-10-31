<?php

namespace Hibira\FilamentSupport\Tests;

use Hibira\Filament\HibiraFilamentServiceProvider;
use Hibira\FilamentSupport\Models\Setting;
use Hibira\FilamentSupport\Realtime;
use Hibira\FilamentSupport\SupportServiceProvider;
use Hibira\FilamentSupport\Tests\Fixtures\AdminPanelProvider;
use Hibira\FilamentSupport\Tests\Fixtures\FakeRealtime;
use Hibira\Laravel\HibiraServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected FakeRealtime $realtime;

    protected function setUp(): void
    {
        parent::setUp();

        $this->realtime = new FakeRealtime;
        $this->app->instance(Realtime::class, $this->realtime);
    }

    protected function getPackageProviders($app)
    {
        // Filament 3, 4 and 5 ship different packages; register the ones installed.
        $providers = array_filter([
            'BladeUI\\Heroicons\\BladeHeroiconsServiceProvider',
            'BladeUI\\Icons\\BladeIconsServiceProvider',
            'RyanChandler\\BladeCaptureDirective\\BladeCaptureDirectiveServiceProvider',
            'Livewire\\LivewireServiceProvider',
            'Filament\\Support\\SupportServiceProvider',
            'Filament\\Actions\\ActionsServiceProvider',
            'Filament\\Forms\\FormsServiceProvider',
            'Filament\\Infolists\\InfolistsServiceProvider',
            'Filament\\Notifications\\NotificationsServiceProvider',
            'Filament\\QueryBuilder\\QueryBuilderServiceProvider',
            'Filament\\Schemas\\SchemasServiceProvider',
            'Filament\\Tables\\TablesServiceProvider',
            'Filament\\Widgets\\WidgetsServiceProvider',
            'Filament\\FilamentServiceProvider',
            HibiraServiceProvider::class,
        ], 'class_exists');

        return array_merge(array_values($providers), [
            HibiraFilamentServiceProvider::class,
            SupportServiceProvider::class,
            AdminPanelProvider::class,
        ]);
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('app.name', 'Acme Shop');
        $app['config']->set('app.url', 'https://shop.example');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('broadcasting.default', 'hibira');
        $app['config']->set('auth.providers.users.model', Fixtures\User::class);
        $app['config']->set('hibira.app_id', '1001');
        $app['config']->set('hibira.key', 'pk_test');
        $app['config']->set('hibira.secret', 'sk_test');
        $app['config']->set('hibira-support.allowed_origins', ['https://www.example.com']);
    }

    protected function defineDatabaseMigrations()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        (require __DIR__.'/../database/migrations/2026_10_31_000000_create_hibira_support_tables.php')->up();
    }

    /** @param  array<string, mixed>  $attributes */
    protected function aiSettings(array $attributes = []): Setting
    {
        return Setting::query()->create($attributes + [
            'ai_mode' => Setting::AUTO,
            'provider' => 'openai',
            'model' => 'gpt-5-mini',
            'api_key' => 'sk-test-1234',
            'knowledge' => 'Shipping takes 2-3 days.',
            'daily_limit' => 200,
        ]);
    }

    /** @return array{0: string, 1: string} conversation id and token */
    protected function startConversation(string $body = 'Hello', array $headers = []): array
    {
        $response = $this->postJson('/hibira-support/conversations', ['body' => $body, 'locale' => 'zh-TW'], $headers)->assertCreated();

        return [$response->json('id'), $response->json('token')];
    }
}
