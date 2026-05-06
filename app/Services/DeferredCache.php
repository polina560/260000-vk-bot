<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class DeferredCache
{
    /**
     * @template TValue
     *
     * @param  Closure(Carbon $start, Carbon $end): TValue  $queryClosure
     * @return TValue
     */
    public function delayedRemember(string $cacheKey, Closure $queryClosure, int $ttlSeconds = 600): mixed
    {
        $now = Carbon::now();
        $start = $now->copy()->startOfDay();
        $end = $now->copy()->endOfDay();

        /** @var array{ cached_at?: int, value?: TValue }|null $cached */
        $cached = Cache::get($cacheKey);

        // Если кеша нет — синхронно выполняем и сохраняем
        if (
            !is_array($cached)
            || !array_key_exists('value', $cached)
            || !array_key_exists('cached_at', $cached)
        ) {
            $data = $queryClosure($start, $end);
            Cache::forever($cacheKey, [
                'value' => $data,
                'cached_at' => $now->timestamp,
            ]);

            return $data;
        }

        if (Carbon::createFromTimestamp($cached['cached_at'])->addSeconds($ttlSeconds)->isPast()) {
            dispatch(static function () use ($cacheKey, $queryClosure, $start, $end): void {
                $data = $queryClosure($start, $end);
                Cache::forever($cacheKey, [
                    'value' => $data,
                    'cached_at' => now()->timestamp,
                ]);
            });
        }

        return $cached['value'];
    }
}
