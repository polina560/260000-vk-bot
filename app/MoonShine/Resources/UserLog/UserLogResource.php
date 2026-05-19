<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\UserLog;

use App\Models\UserLog;
use App\MoonShine\Resources\UserLog\Pages\UserLogIndexPage;
use App\MoonShine\Resources\UserLog\Pages\UserLogFormPage;
use App\MoonShine\Resources\UserLog\Pages\UserLogDetailPage;

use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Contracts\Core\PageContract;

/**
 * @extends ModelResource<UserLog, UserLogIndexPage, UserLogFormPage, UserLogDetailPage>
 */
class UserLogResource extends ModelResource
{
    protected string $model = UserLog::class;

	protected array $with = ['telegramUser'];

    protected string $title = 'Логи ползователей';

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            UserLogIndexPage::class,
            UserLogFormPage::class,
            UserLogDetailPage::class,
        ];
    }
}
