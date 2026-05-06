<?php

namespace App\MoonShine\Resources\Text\Pages;

use App\MoonShine\Resources\Text\TextResource;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use Override;

/**
 * @extends IndexPage<TextResource>
 */
class TextIndexPage extends IndexPage
{
    #[Override]
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Ключ', 'key'),
            Text::make('Значение', 'value'),
        ];
    }
}
