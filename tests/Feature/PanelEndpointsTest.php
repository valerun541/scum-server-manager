<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\TestCase;

class PanelEndpointsTest extends TestCase
{
    public function test_unauthenticated_panel_routes_redirect_to_login(): void
    {
        $routes = [
            '/servers',
            '/players',
            '/scumdb',
            '/rcon',
            '/plugins/vip',
            '/logs/chat',
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);
            $response->assertStatus(302);
            $response->assertRedirect(route('login'));
        }
    }

    public function test_authenticated_panel_routes_render_successfully(): void
    {
        $routes = [
            '/servers',
            '/players',
            '/scumdb',
            '/scumdb/vehicles',
            '/scumdb/flags',
            '/scumdb/banks',
            '/scumdb/economy',
            '/rcon',
            '/wargm',
            '/automation',
            '/config-files',
            '/map',
            '/packs',
            '/account/settings',
            '/plugins/vip',
            '/plugins/insurance',
            '/plugins/airdrop',
            '/plugins/savehome',
            '/plugins/teleport',
            '/plugins/rewards',
            '/logs/chat',
            '/logs/rcon',
            '/logs/system',
            '/logs/events',
            '/logs/admin',
        ];

        foreach ($routes as $route) {
            $response = $this->withSession(['scum_auth' => true])->get($route);
            $this->assertEquals(200, $response->getStatusCode(), "Route $route returned status ".$response->getStatusCode());
        }
    }

    public function test_security_whitelist_redirects_to_account_settings(): void
    {
        $response = $this->withSession(['scum_auth' => true])->get('/security/whitelist');
        $response->assertStatus(302);
        $response->assertRedirect(route('account.settings'));
    }

    public function test_players_action_requires_authentication(): void
    {
        $response = $this->withoutMiddleware([PreventRequestForgery::class])
            ->post('/players/action', [
                'action' => 'give_money',
                'steam_id' => '76561198000000000',
            ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    public function test_players_action_validation_fails_with_invalid_action(): void
    {
        $response = $this->withoutMiddleware([PreventRequestForgery::class])
            ->withSession(['scum_auth' => true])
            ->post('/players/action', [
                'action' => 'unknown_invalid_action',
                'steam_id' => '76561198000000000',
            ]);

        $response->assertSessionHasErrors(['action']);
    }
}
