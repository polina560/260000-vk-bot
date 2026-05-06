<?php

namespace Tests\Feature\Moonshine;

use Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MoonshineCreateUserTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function create_user_successfully(): void
    {
        $this->artisan('moonshine:create-user')->assertSuccessful();
        $this->assertDatabaseHas('moonshine_users', [
            'name' => Config::string('moonshine.default_name'),
            'email' => Config::string('moonshine.default_username'),
        ]);
        $this->artisan('moonshine:create-user')->assertSuccessful();
    }
}
