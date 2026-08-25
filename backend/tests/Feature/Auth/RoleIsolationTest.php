<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_cannot_access_the_admin_ping_route(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->getJson('/api/admin/ping');

        $response->assertStatus(403);
    }

    public function test_an_admin_cannot_access_the_customer_ping_route(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->getJson('/api/customer/ping');

        $response->assertStatus(403);
    }

    public function test_an_admin_can_access_the_admin_ping_route(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->getJson('/api/admin/ping');

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
    }

    public function test_a_customer_can_access_the_customer_ping_route(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->getJson('/api/customer/ping');

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
    }

    public function test_unauthenticated_requests_to_the_admin_ping_route_are_rejected(): void
    {
        $this->getJson('/api/admin/ping')->assertStatus(401);
    }

    public function test_unauthenticated_requests_to_the_customer_ping_route_are_rejected(): void
    {
        $this->getJson('/api/customer/ping')->assertStatus(401);
    }
}
