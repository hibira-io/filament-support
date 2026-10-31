<?php

namespace Hibira\FilamentSupport\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the widget call the visitor API from the sites listed in
 * hibira-support.allowed_origins, and answers their preflight requests.
 */
class AllowWidgetOrigins
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');
        $allowed = $origin !== null && in_array(rtrim($origin, '/'), static::origins(), true);

        if ($origin !== null && ! $allowed && $origin !== $request->getSchemeAndHttpHost()) {
            return response()->json(['message' => 'This site may not use the chat.'], 403);
        }

        $response = $request->isMethod('OPTIONS') ? response('', 204) : $next($request);

        if ($allowed) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, X-Hibira-Support-Token');
            $response->headers->set('Access-Control-Max-Age', '600');
            $response->headers->set('Vary', 'Origin', false);
        }

        return $response;
    }

    /** @return list<string> */
    public static function origins(): array
    {
        $url = parse_url((string) config('app.url'));
        $own = isset($url['scheme'], $url['host'])
            ? $url['scheme'].'://'.$url['host'].(isset($url['port']) ? ':'.$url['port'] : '')
            : null;

        return array_values(array_filter(array_map(
            fn ($origin) => rtrim((string) $origin, '/'),
            [$own, ...(array) config('hibira-support.allowed_origins', [])],
        )));
    }
}
