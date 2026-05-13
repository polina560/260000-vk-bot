<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\TelegramUser;

use App\Models\TelegramUser;
use App\MoonShine\Resources\TelegramUser\Pages\TelegramUserIndexPage;
use App\MoonShine\Resources\TelegramUser\Pages\TelegramUserFormPage;
use App\MoonShine\Resources\TelegramUser\Pages\TelegramUserDetailPage;

use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Contracts\Core\PageContract;

/**
 * @extends ModelResource<TelegramUser, TelegramUserIndexPage, TelegramUserFormPage, TelegramUserDetailPage>
 */
class TelegramUserResource extends ModelResource
{
    protected string $model = TelegramUser::class;

    protected string $title = 'Пользователи телеграм';

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            TelegramUserIndexPage::class,
            TelegramUserFormPage::class,
            TelegramUserDetailPage::class,
        ];
    }
}
