<?php

namespace Hibira\FilamentSupport\Http;

use Hibira\FilamentSupport\Desk;
use Hibira\FilamentSupport\Models\Conversation;
use Hibira\FilamentSupport\Models\Message;
use Hibira\FilamentSupport\Models\Setting;
use Hibira\FilamentSupport\Realtime;
use Hibira\Laravel\Hibira;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The API behind the website chat widget. Visitors are not signed in: each
 * conversation has a random token the widget keeps in the browser.
 */
class VisitorController
{
    public const LOCALES = ['en', 'zh_TW', 'zh_CN', 'ja', 'ko', 'th', 'vi'];

    public function widget(): Response
    {
        return response(file_get_contents(__DIR__.'/../../resources/js/widget.js'), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function config(Request $request): JsonResponse
    {
        $settings = Setting::current();
        $locale = static::locale($request->query('locale'));
        $hibira = config('hibira');
        $scheme = $hibira['scheme'] ?? 'https';

        return response()->json([
            'key' => (string) ($hibira['key'] ?? ''),
            'host' => $hibira['host'] ?? Hibira::DEFAULT_HOST,
            'port' => (int) ($hibira['port'] ?? ($scheme === 'https' ? 443 : 80)),
            'tls' => $scheme === 'https',
            'pusher_js' => config('hibira-support.pusher_js'),
            'title' => $settings->title ?: config('app.name'),
            'greeting' => $settings->greeting ?: trans('hibira-support::widget.greeting', [], $locale),
            'color' => $settings->color(),
            'powered_by' => (bool) config('hibira-support.powered_by', true),
            'ai' => $settings->aiEnabled() && $settings->ai_mode === Setting::AUTO,
            'locale' => $locale,
            'strings' => trans('hibira-support::widget', [], $locale),
        ]);
    }

    public function start(Request $request, Desk $desk): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.config('hibira-support.message_characters', 2000)],
            'name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:200'],
            'page_url' => ['nullable', 'string', 'max:500'],
            'locale' => ['nullable', 'string', 'max:16'],
        ]);

        $ip = static::ipHash($request);
        $key = 'hibira-support:start:'.$ip;

        if (RateLimiter::tooManyAttempts($key, config('hibira-support.limits.conversations_per_ip_per_hour', 10))) {
            return response()->json(['message' => trans('hibira-support::widget.slow_down', [], static::locale($data['locale'] ?? null))], 429);
        }

        RateLimiter::hit($key, 3600);

        [$conversation, $token] = Conversation::start([
            'visitor_name' => $data['name'] ?? null,
            'visitor_email' => $data['email'] ?? null,
            'page_url' => $data['page_url'] ?? null,
            'locale' => static::locale($data['locale'] ?? null),
            'ip_hash' => $ip,
        ]);

        $message = $desk->visitorMessage($conversation, trim($data['body']));

        return response()->json([
            'id' => $conversation->id,
            'token' => $token,
            'channel' => $conversation->channel(),
            'messages' => [$message->present()],
        ], 201);
    }

    public function messages(Request $request, string $conversation): JsonResponse
    {
        $conversation = $this->conversation($request, $conversation);
        $after = (int) $request->query('after', 0);

        $messages = $conversation->messages()
            ->where('id', '>', $after)
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->map(fn (Message $m) => $m->present());

        return response()->json([
            'status' => $conversation->status,
            'channel' => $conversation->channel(),
            'messages' => $messages,
        ]);
    }

    public function send(Request $request, Desk $desk, string $conversation): JsonResponse
    {
        $conversation = $this->conversation($request, $conversation);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.config('hibira-support.message_characters', 2000)],
        ]);

        $key = 'hibira-support:send:'.$conversation->id;

        if (RateLimiter::tooManyAttempts($key, config('hibira-support.limits.messages_per_minute', 20))) {
            return response()->json(['message' => trans('hibira-support::widget.slow_down', [], static::locale($conversation->locale))], 429);
        }

        RateLimiter::hit($key, 60);

        return response()->json($desk->visitorMessage($conversation, trim($data['body']))->present(), 201);
    }

    /** Signs the visitor's own private channel for pusher-js. */
    public function auth(Request $request, Realtime $realtime, string $conversation): JsonResponse
    {
        $conversation = $this->conversation($request, $conversation);

        if ($request->input('channel_name') !== $conversation->channel() || ! is_string($request->input('socket_id'))) {
            abort(403);
        }

        try {
            return response()->json($realtime->authorize($conversation->channel(), $request->input('socket_id')));
        } catch (\Throwable) {
            abort(403);
        }
    }

    protected function conversation(Request $request, string $id): Conversation
    {
        $conversation = Conversation::find($id);

        if ($conversation === null || ! $conversation->checkToken($request->header('X-Hibira-Support-Token'))) {
            abort(404);
        }

        return $conversation;
    }

    public static function locale(?string $locale): string
    {
        $locale = str_replace('-', '_', (string) $locale);

        if (in_array($locale, self::LOCALES, true)) {
            return $locale;
        }

        $lower = strtolower($locale);

        return match (true) {
            str_starts_with($lower, 'zh_tw'), str_starts_with($lower, 'zh_hk'), str_starts_with($lower, 'zh_hant') => 'zh_TW',
            str_starts_with($lower, 'zh') => 'zh_CN',
            in_array(substr($lower, 0, 2), ['ja', 'ko', 'th', 'vi'], true) => substr($lower, 0, 2),
            default => 'en',
        };
    }

    protected static function ipHash(Request $request): string
    {
        return hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'));
    }
}
