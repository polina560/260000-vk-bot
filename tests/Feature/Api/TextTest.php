<?php

namespace Tests\Feature\Api;

use App\Models\Text;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TextTest extends TestCase
{
    use RefreshDatabase;

    public function test_index(): void
    {
        Text::factory()->count(10)->create();
        $response = $this->get('/api/texts');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            '*' => [
                'key',
                'value',
            ],
        ]);
    }
}
