<?php

namespace Hibira\FilamentSupport\Tests\Fixtures;

use Hibira\FilamentSupport\Realtime;

class FakeRealtime extends Realtime
{
    /** @var list<array{channel: string, event: string, data: array<string, mixed>}> */
    public array $sent = [];

    public function __construct() {}

    public function trigger(string $channel, string $event, array $data): void
    {
        $this->sent[] = compact('channel', 'event', 'data');
    }

    public function authorize(string $channel, string $socketId): array
    {
        return ['auth' => 'pk_test:signed:'.$channel.':'.$socketId];
    }

    /** @return list<array{channel: string, event: string, data: array<string, mixed>}> */
    public function on(string $channel, ?string $event = null): array
    {
        return array_values(array_filter($this->sent, fn ($s) => $s['channel'] === $channel && ($event === null || $s['event'] === $event)));
    }
}
