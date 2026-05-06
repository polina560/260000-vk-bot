<?php

namespace App\MoonShine\Components;

use App\Facades\DeferredCache;
use Closure;
use MoonShine\UI\Components\Metrics\Wrapped\ValueMetric;
use Throwable;

/**
 * @method static make(string $label, string $cacheKey, Closure $value)
 */
class FragmentValueMetric extends AppFragment
{
    /**
     * @param  Closure(): (float|int|string)  $value
     *
     * @throws Throwable
     */
    public function __construct(string $label, string $cacheKey, Closure $value)
    {
        $components = [ValueMetric::make($label)];
        parent::__construct($components);

        $this->lazyLoad(fn(): array => [
            ValueMetric::make($label)
                ->value(DeferredCache::delayedRemember($cacheKey, $value)),
        ]);
    }
}
