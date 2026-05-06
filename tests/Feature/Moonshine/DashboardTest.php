<?php

namespace Tests\Feature\Moonshine;

use App\Models\MoonshineUser;
use App\MoonShine\Pages\Dashboard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Pages\Page;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected Page $page {
        get {
            return $this->page;
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

        $this->page = app(Dashboard::class);

        $this->user = MoonshineUser::factory()->create();

        $this->be($this->user, 'moonshine');
    }

    #[Test]
    public function dashboard_page_successful(): void
    {
        $response = $this->get(
            $this->page->getUrl(),
        );

        $response->assertSuccessful();
    }

    #[Test]
    public function dashboard_users_metric_successful(): void
    {
        $response = $this->get(
            $this->page->getUrl().'?_fragment-load=app_fragment_1',
        );

        $response->assertSuccessful();
    }
}
