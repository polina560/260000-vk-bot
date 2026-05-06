<?php

namespace Tests\Feature\Moonshine;

use App\Models\MoonshineUser;
use App\MoonShine\Resources\MoonShineUser\MoonShineUserResource;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Resources\ModelResource;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MoonshineUserResourceTest extends TestCase
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

        $this->resource = app(MoonShineUserResource::class);

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
    public function create_page_successful(): void
    {
        $response = $this->get(
            $this->resource->getFormPageUrl(),
        );

        $response->assertSuccessful();
    }

    #[Test]
    public function edit_page_successful(): void
    {
        $item = MoonshineUser::factory()->create();

        $response = $this->get(
            $this->resource->getFormPageUrl($item->id),
        );

        $response->assertSuccessful();
    }
}
