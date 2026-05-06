<?php

namespace App\MoonShine\Resources\Text\Pages;

use App\Models\Text as TextModel;
use App\MoonShine\Resources\Text\TextResource;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use Override;

/**
 * @extends FormPage<TextResource, TextModel>
 */
class TextFormPage extends FormPage
{
    #[Override]
    protected function fields(): iterable
    {
        return [
            ID::make(),
            Text::make('Ключ', 'key'),
            Text::make('Значение', 'value'),
        ];
    }

    /**
     * @param  TextModel  $item
     * @return array<string, string[]|string>
     *
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    #[Override]
    protected function rules(mixed $item): array
    {
        return [
            'key' => ['required', 'string', 'max:255'],
            'value' => ['required', 'string', 'max:65535'],
        ];
    }
}
