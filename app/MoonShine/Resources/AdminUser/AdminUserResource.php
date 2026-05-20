<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\AdminUser;

use App\Models\AdminUser;
use App\MoonShine\Resources\AdminUser\Pages\AdminUserIndexPage;
use App\MoonShine\Resources\AdminUser\Pages\AdminUserFormPage;
use App\MoonShine\Resources\AdminUser\Pages\AdminUserDetailPage;

use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Contracts\Core\PageContract;

/**
 * @extends ModelResource<AdminUser, AdminUserIndexPage, AdminUserFormPage, AdminUserDetailPage>
 */
class AdminUserResource extends ModelResource
{
    protected string $model = AdminUser::class;

    protected string $title = 'Администраторы';

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            AdminUserIndexPage::class,
            AdminUserFormPage::class,
            AdminUserDetailPage::class,
        ];
    }
}
