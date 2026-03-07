<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class PushRateLimiter
{
    private const MAX_PER_SECOND = 10;

    private const CACHE_KEY_PREFIX = 'push_rate:';

    /**
     * Block until a send slot is available (max 10 per second).
     */
    public function acquire(): void
    {
        $second = (string) time();
        $key = self::CACHE_KEY_PREFIX.$second;
        $count = (int) Cache::get($key, 0);

        if ($count >= self::MAX_PER_SECOND) {
            $this->sleepUntilNextSecond();
            $this->acquire();

            return;
        }

        Cache::put($key, $count + 1, 2);
    }

    private function sleepUntilNextSecond(): void
    {
        $now = microtime(true);
        $next = (int) ceil($now);
        $wait = $next - $now;
        if ($wait > 0 && $wait <= 1.0) {
            usleep((int) round($wait * 1_000_000));
        }
    }
}
