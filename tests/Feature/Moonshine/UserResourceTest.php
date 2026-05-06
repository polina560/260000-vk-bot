<?php

namespace Tests\Feature\Moonshine;

use App\Models\MoonshineUser;
use App\Models\User;
use App\MoonShine\Resources\User\UserResource;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Resources\ModelResource;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected ModelResource $resource {
        get {
            return $this->resource;
        }
    }

    protected Authenticatable|MoonshineUser $user {
        get {
            return $this->user;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->resource = app(UserResource::class);

        $this->user = MoonshineUser::factory()->create();

        $this->be($this->user, 'moonshine');
    }

    #[Test]
    public function index_page_successful(): void
    {
        $response = $this->get(
            $this->resource->getIndexPageUrl(),
        );

        $response->assertSuccessful();
    }

    #[Test]
    public function detail_page_successful(): void
    {
        $item = User::factory()->create();

        $response = $this->get(
            $this->resource->getDetailPageUrl($item->id),
        );

        $response->assertSuccessful();
    }
}
