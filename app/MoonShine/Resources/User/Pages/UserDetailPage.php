<?php

namespace App\MoonShine\Resources\User\Pages;

use App\MoonShine\Resources\User\UserResource;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\UI\Fields\Email;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use Override;

/**
 * @extends DetailPage<UserResource>
 */
class UserDetailPage extends DetailPage
{
    #[Override]
    protected function fields(): iterable
    {
        return [
            ID::make(),
            Text::make('Имя', 'name'),
            Email::make('Email', 'email'),
        ];
    }
}
