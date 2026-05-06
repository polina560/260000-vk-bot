<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\User;

use App\Models\User;
use App\MoonShine\Handlers\AppExportHandler;
use App\MoonShine\Resources\User\Pages\UserDetailPage;
use App\MoonShine\Resources\User\Pages\UserIndexPage;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\Handlers\Handler;
use MoonShine\ImportExport\Contracts\HasImportExportContract;
use MoonShine\ImportExport\Traits\ImportExportConcern;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;
use MoonShine\UI\Fields\Email;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use Override;

/**
 * @extends ModelResource<User, UserIndexPage, null, UserDetailPage>
 */
#[Icon('user-group')]
class UserResource extends ModelResource implements HasImportExportContract
{
    use ImportExportConcern;

    protected string $model = User::class;

    protected string $title = 'Пользователи';

    /**
     * @return list<FieldContract>
     */
    protected function exportFields(): iterable
    {
        return [
            ID::make(),
            Text::make('Name'),
            Email::make('Email'),
        ];
    }

    protected function export(): ?Handler
    {
        return AppExportHandler::make(__('moonshine::ui.export'))
            ->queue()
            ->filename(sprintf('export_users_%s', date('Ymd-His')))
            ->dir('/exports')
            ->notifyUsers(fn(): array => ($id = auth()->id()) ? [$id] : []);
    }

    protected function import(): ?Handler
    {
        return null;
    }

    #[Override]
    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::CREATE, Action::UPDATE);
    }

    #[Override]
    protected function pages(): array
    {
        return [
            UserIndexPage::class,
            UserDetailPage::class,
        ];
    }
}
