<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'role' => $user->role->value,
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_is_rejected_with_an_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_a_user_can_logout_and_the_session_ends(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->getJson('/api/me')->assertStatus(200);

        $response = $this->postJson('/api/logout');
        $response->assertStatus(200);

        $this->assertGuest();

        $this->getJson('/api/me')->assertStatus(401);
    }
}
