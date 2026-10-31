<?php

namespace Hibira\FilamentSupport;

use Hibira\FilamentSupport\Http\Middleware\AllowWidgetOrigins;
use Hibira\FilamentSupport\Http\VisitorController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SupportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/hibira-support.php', 'hibira-support');
        $this->app->singleton(Realtime::class);
        $this->app->singleton(Desk::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'hibira-support');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'hibira-support');

        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/hibira-support.php' => config_path('hibira-support.php'),
            ], 'hibira-support-config');

            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/hibira-support'),
            ], 'hibira-support-translations');
        }
    }

    protected function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::prefix(trim((string) config('hibira-support.path', 'hibira-support'), '/'))
            ->middleware([AllowWidgetOrigins::class])
            ->name('hibira-support.')
            ->group(function () {
                Route::get('widget.js', [VisitorController::class, 'widget'])->name('widget');
                Route::get('config', [VisitorController::class, 'config'])->name('config');
                Route::post('conversations', [VisitorController::class, 'start'])->name('start');
                Route::get('conversations/{conversation}', [VisitorController::class, 'messages'])->name('messages');
                Route::post('conversations/{conversation}/messages', [VisitorController::class, 'send'])->name('send');
                Route::post('conversations/{conversation}/auth', [VisitorController::class, 'auth'])->name('auth');
                Route::options('{any}', fn () => response('', 204))->where('any', '.*')->name('preflight');
            });
    }
}
