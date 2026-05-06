<?php

namespace App\MoonShine\Resources\Text\Pages;

use App\MoonShine\Resources\Text\TextResource;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use Override;

/**
 * @extends DetailPage<TextResource>
 */
class TextDetailPage extends DetailPage
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
