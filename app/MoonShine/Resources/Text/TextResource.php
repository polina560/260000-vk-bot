<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Text;

use App\Models\Text as TextModel;
use App\MoonShine\Resources\MoonShineUser\MoonShineUserResource;
use App\MoonShine\Resources\Text\Pages\TextDetailPage;
use App\MoonShine\Resources\Text\Pages\TextFormPage;
use App\MoonShine\Resources\Text\Pages\TextIndexPage;
use MoonShine\ChangeLog\Components\ChangeLog;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Layer;
use Override;

/**
 * @extends ModelResource<TextModel, TextIndexPage, TextFormPage, TextDetailPage>
 */
#[Icon('document-text')]
class TextResource extends ModelResource
{
    protected string $model = TextModel::class;

    protected string $title = 'Тексты';

    protected string $column = 'key';

    protected bool $createInModal = true;

    protected bool $editInModal = true;

    protected bool $detailInModal = true;

    #[Override]
    protected function onLoad(): void
    {
        $this->getFormPage()?->pushToLayer(
            Layer::BOTTOM,
            ChangeLog::make('Лог изменений', $this, MoonShineUserResource::class),
        );
    }

    #[Override]
    protected function pages(): array
    {
        return [
            TextIndexPage::class,
            TextFormPage::class,
            TextDetailPage::class,
        ];
    }
}
