<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class CatalogEndpointTest extends TestCase
{
    public function test_unauthenticated_user_cannot_access_catalog(): void
    {
        $this->get('/catalog/items')->assertRedirect('/login');
        $this->get('/catalog/vehicles')->assertRedirect('/login');
        $this->get('/catalog/markers')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_query_catalog_items(): void
    {
        $response = $this->withSession(['scum_auth' => true])
            ->get('/catalog/items?limit=5');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'ok',
                'data' => [
                    '*' => ['id', 'name', 'category', 'command'],
                ],
                'count',
            ]);
    }

    public function test_authenticated_user_can_query_vehicles(): void
    {
        $response = $this->withSession(['scum_auth' => true])
            ->get('/catalog/vehicles');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'ok',
                'data' => [
                    '*' => ['id', 'name', 'type', 'command'],
                ],
                'count',
            ]);
    }

    public function test_authenticated_user_can_query_markers(): void
    {
        $response = $this->withSession(['scum_auth' => true])
            ->get('/catalog/markers?limit=5');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'ok',
                'data' => [
                    '*' => ['name', 'type', 'x', 'y', 'z', 'command'],
                ],
                'count',
            ]);
    }
}
