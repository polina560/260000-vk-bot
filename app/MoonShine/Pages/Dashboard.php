<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Models\User;
use App\MoonShine\Components\AppFragment;
use App\MoonShine\Components\FragmentValueMetric;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\MenuManager\Attributes\SkipMenu;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Grid;
use Override;

#[SkipMenu]
class Dashboard extends Page
{
    /**
     * @return array<string, string>
     */
    #[Override]
    public function getBreadcrumbs(): array
    {
        return [
            '#' => $this->getTitle(),
        ];
    }

    #[Override]
    public function getTitle(): string
    {
        return $this->title ?: 'Dashboard';
    }

    /**
     * @return list<ComponentContract>
     */
    protected function components(): iterable
    {
        AppFragment::resetUniqueId();

        return [
            Grid::make([
                Column::make([
                    FragmentValueMetric::make('Всего пользователей', 'users_count', static fn() => User::count()),
                ])->columnSpan(3),
            ]),
        ];
    }
}
