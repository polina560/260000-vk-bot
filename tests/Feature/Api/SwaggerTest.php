<?php

namespace Tests\Feature\Api;

use App\Models\MoonshineUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SwaggerTest extends TestCase
{
    use RefreshDatabase;

    protected Authenticatable|MoonshineUser $user {
        get {
            return $this->user;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = MoonshineUser::factory()->create();

        $this->be($this->user, 'moonshine');
    }

    #[Test]
    public function swagger_page_protected(): void
    {
        $this->get('/api/docs')->assertClientError();
        $this->get('/api/openapi-json')->assertClientError();
    }

    #[Test]
    public function swagger_page_available(): void
    {
        $headers = ['Authorization' => 'Basic '.base64_encode($this->user->email.':password')];
        $this->get('/api/docs', $headers)->assertSuccessful();
        $this->get('/api/openapi-json', $headers)->assertSuccessful();
    }
}
