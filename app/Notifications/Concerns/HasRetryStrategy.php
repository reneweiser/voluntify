<?php

namespace App\Notifications\Concerns;

use Illuminate\Support\Facades\Log;

trait HasRetryStrategy
{
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 300];

    public function failed(\Throwable $exception): void
    {
        Log::error('Queued notification failed permanently', [
            'notification' => static::class,
            'exception' => $exception->getMessage(),
        ]);
    }
}
