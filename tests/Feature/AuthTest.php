<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/');
        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('login'));
        $response->assertStatus(200);
        $response->assertSee('Войти');
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $response = $this->withoutMiddleware([PreventRequestForgery::class])
            ->post(route('login.post'), [
                'username' => 'wrong_user_test',
                'password' => 'wrong_password_test',
            ]);

        $response->assertSessionHasErrors(['password']);
        $this->assertFalse(session()->has('scum_auth'));
    }

    public function test_authenticated_user_can_access_servers_dashboard(): void
    {
        $response = $this->withSession(['scum_auth' => true])->get(route('servers.index'));
        $response->assertStatus(200);
    }

    public function test_user_can_logout(): void
    {
        $response = $this->withoutMiddleware([PreventRequestForgery::class])
            ->withSession(['scum_auth' => true])
            ->post(route('logout'));

        $response->assertRedirect(route('login'));
        $response->assertSessionMissing('scum_auth');
    }
}
