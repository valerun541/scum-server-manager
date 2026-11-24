<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiEndpointsTest extends TestCase
{
    public function test_api_status_requires_valid_token(): void
    {
        $response = $this->getJson('/api/v1/status');
        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'error' => 'Unauthorized. Invalid or missing API token.',
        ]);
    }

    public function test_api_status_succeeds_with_valid_token(): void
    {
        $token = DB::table('app_settings')->where('key', 'api_token')->value('value');
        if (! $token) {
            $token = 'test_token_'.bin2hex(random_bytes(8));
            DB::table('app_settings')->updateOrInsert(
                ['key' => 'api_token'],
                ['value' => $token, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $response = $this->withHeader('X-API-Key', $token)->getJson('/api/v1/status');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'server',
            ],
        ]);
    }
}
