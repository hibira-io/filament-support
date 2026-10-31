<?php

namespace Hibira\FilamentSupport;

use Hibira\Laravel\Hibira;
use Throwable;

/**
 * Sends the desk's events over Hibira.
 *
 * A failed send is reported but never fails the request: messages are
 * stored first, and the widget and inbox fetch them again on reconnect.
 */
class Realtime
{
    /** Events larger than this carry only the message id; Hibira's Free plan allows 4 KB. */
    public const MAX_PAYLOAD = 3500;

    public function __construct(protected Hibira $hibira) {}

    /** @param  array<string, mixed>  $data */
    public function trigger(string $channel, string $event, array $data): void
    {
        try {
            $this->hibira->realtime()->trigger($channel, $event, $data);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** @return array<string, mixed> the auth response pusher-js expects */
    public function authorize(string $channel, string $socketId): array
    {
        return json_decode($this->hibira->realtime()->authorizeChannel($channel, $socketId), true);
    }

    /**
     * The message itself when it fits in one event, or just its id so the
     * browser fetches it.
     *
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    public static function fit(array $message): array
    {
        return strlen((string) json_encode($message)) <= self::MAX_PAYLOAD
            ? $message
            : ['id' => $message['id'], 'fetch' => true];
    }
}
