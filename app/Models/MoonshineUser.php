<?php

namespace App\Models;

use Database\Factories\MoonshineUserFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use MoonShine\Laravel\Models\MoonshineUser as User;
use MoonShine\Permissions\Traits\HasMoonShinePermissions;
use MoonShine\TwoFactor\Traits\TwoFactorAuthenticatable;
use Override;

class MoonshineUser extends User
{
    use HasMoonShinePermissions;
    use TwoFactorAuthenticatable;

    #[Override]
    protected static function newFactory(): Factory
    {
        return MoonshineUserFactory::new();
    }
}
