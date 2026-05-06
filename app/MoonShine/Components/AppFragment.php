<?php

declare(strict_types=1);

namespace App\MoonShine\Components;

use Closure;
use Illuminate\Support\Collection;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Crud\Components\Fragment;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Div;
use MoonShine\UI\Components\Loader;

// Импортируем как BaseFragment

class AppFragment extends Fragment
{
    private static int $uniqueId = 0;

    public static function resetUniqueId(): void
    {
        self::$uniqueId = 0;
    }

    /**
     * @param  Closure(): iterable<ComponentContract>  $content
     */
    public function lazyLoad(Closure $content): self
    {
        if ($this->name === 'default') {
            $this->name('app_fragment_'.++self::$uniqueId);
        }
        if (request()->query('_fragment-load') !== $this->name) {
            // Обертка прелоадером
            $this->components = [
                Div::make(
                    Collection::make((array) $this->components)
                        ->prepend(Loader::make()
                            ->style([
                                'position: absolute',
                                'top: 50%',
                                'left: 50%',
                                'transform: translate(-50%,-50%)',
                                'z-index: 10',
                            ])),
                )->style(['position: relative']),
            ];
            // Кнопка для инициации загрузки контента
            $this->components[] = ActionButton::make('Обновить метрики')
                ->dispatchEvent(AlpineJs::event(JsEvent::FRAGMENT_UPDATED, $this->name))
                ->customAttributes(['x-init' => '$nextTick(() => $el.click());', 'style' => 'display: none']);
        } else {
            // Если фрагмент уже загружен, то убираем прелоадер и показываем контент
            $this->components = $content();
        }

        return $this;
    }
}
