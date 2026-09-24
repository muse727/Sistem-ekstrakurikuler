<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class HealthTest extends TestCase
{
    /**
     * Test health check endpoint returns 200 and expected json structure.
     */
    public function test_health_check_returns_success(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'API is healthy',
            ]);
    }
}
