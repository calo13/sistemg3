<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_log_out(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_guests_cannot_access_the_dashboard_or_profile(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/user/profile')->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_access_the_dashboard_and_profile(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/dashboard')->assertOk()->assertSee($user->name);
        $this->get('/user/profile')->assertOk()->assertSee($user->email);
    }

    public function test_repeated_failed_logins_are_throttled(): void
    {
        $user = User::factory()->create();
        $credentials = ['email' => $user->email, 'password' => 'wrong-password'];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', $credentials)->assertSessionHasErrors('email');
        }

        $this->post('/login', $credentials)->assertStatus(429);
        $this->assertGuest();
    }
}
