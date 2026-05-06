<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use App\MoonShine\Resources\Text\TextResource;
use App\MoonShine\Resources\User\UserResource;
use MoonShine\ColorManager\Palettes\PurplePalette;
use MoonShine\Contracts\ColorManager\PaletteContract;
use MoonShine\Laravel\Layouts\AppLayout;
use MoonShine\MenuManager\MenuItem;
use Override;

final class MoonShineLayout extends AppLayout
{
    /**
     * @var null|class-string<PaletteContract>
     */
    protected ?string $palette = PurplePalette::class;

    #[Override]
    protected function assets(): array
    {
        return [
            ...parent::assets(),
        ];
    }

    #[Override]
    protected function menu(): array
    {
        return [
            ...parent::menu(),
            MenuItem::make(UserResource::class),
            MenuItem::make(TextResource::class),
        ];
    }

    #[Override]
    protected function getFooterCopyright(): string
    {
        return <<<'HTML'
            <a href="https://moonshine-laravel.com" target="_blank">
                MoonShine
            </a>
            HTML;
    }
}
