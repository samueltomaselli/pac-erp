<?php

namespace Tests\Feature\Customers;

use App\Enums\CustomerSegment;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerLoginEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_registered_customer_can_log_in_and_reach_its_own_area(): void
    {
        $admin = User::factory()->admin()->create();

        $registration = $this->actingAs($admin)->postJson('/api/admin/customers', [
            'name' => 'Imobiliária Alvorada',
            'document' => '11.222.333/0001-81',
            'email' => 'contato@alvorada.com.br',
            'phone' => '(11) 98888-7777',
            'contact_name' => 'Marina Alves',
            'segment' => CustomerSegment::RealEstate->value,
        ])->assertStatus(201);

        $password = $registration->json('generated_password');
        $customerId = $registration->json('data.id');

        $this->actingAs($admin)->postJson('/api/admin/tasks', [
            'customer_id' => $customerId,
            'title' => 'Enviar documentação inicial',
            'due_date' => today()->addDays(5)->toDateString(),
            'priority' => 'high',
        ])->assertStatus(201);

        $this->postJson('/api/logout')->assertStatus(200);
        $this->freshBrowserSession();
        $this->assertGuest();

        $login = $this->postJson('/api/login', [
            'email' => 'contato@alvorada.com.br',
            'password' => $password,
        ]);

        $login->assertStatus(200);
        $login->assertJsonPath('role', UserRole::Customer->value);
        $login->assertJsonPath('user.email', 'contato@alvorada.com.br');
        $this->assertAuthenticated();

        $this->getJson('/api/me')
            ->assertStatus(200)
            ->assertJsonPath('role', UserRole::Customer->value);

        $this->getJson('/api/customer/ping')->assertStatus(200)->assertJson(['ok' => true]);

        $this->getJson('/api/customer/profile')
            ->assertStatus(200)
            ->assertJsonPath('data.id', $customerId)
            ->assertJsonPath('data.name', 'Imobiliária Alvorada')
            ->assertJsonPath('data.contact_name', 'Marina Alves')
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonPath('data.tasks.0.title', 'Enviar documentação inicial');

        $this->getJson('/api/admin/customers')->assertStatus(403);

        $this->postJson('/api/logout')->assertStatus(200);
        $this->assertGuest();
        $this->getJson('/api/customer/profile')->assertStatus(401);
    }

    public function test_the_generated_credential_is_not_guessable_from_the_registration_data(): void
    {
        $admin = User::factory()->admin()->create();

        $registration = $this->actingAs($admin)->postJson('/api/admin/customers', [
            'name' => 'Corban Prata',
            'document' => '52998224725',
            'email' => 'financeiro@prata.com.br',
            'segment' => CustomerSegment::Corban->value,
        ])->assertStatus(201);

        $this->postJson('/api/logout');
        $this->freshBrowserSession();

        foreach (['52998224725', 'financeiro@prata.com.br', 'Corban Prata', 'password'] as $guess) {
            $this->postJson('/api/login', [
                'email' => 'financeiro@prata.com.br',
                'password' => $guess,
            ])->assertStatus(422);

            $this->assertGuest();
        }

        $this->postJson('/api/login', [
            'email' => 'financeiro@prata.com.br',
            'password' => $registration->json('generated_password'),
        ])->assertStatus(200);
    }

    public function test_a_customer_only_ever_sees_its_own_profile(): void
    {
        $customer = Customer::factory()->create(['name' => 'Cliente Próprio']);
        $other = Customer::factory()->create(['name' => 'Cliente Alheio']);
        Task::factory()->for($other)->create(['title' => 'Tarefa alheia']);

        $this->actingAs($customer->user)->getJson('/api/customer/profile')
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Cliente Próprio')
            ->assertJsonCount(0, 'data.tasks')
            ->assertJsonMissing(['title' => 'Tarefa alheia']);
    }

    public function test_an_admin_without_a_customer_record_gets_no_profile(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/api/customer/profile')->assertStatus(403);
    }

    public function test_a_customer_login_without_a_linked_record_reports_not_found(): void
    {
        $orphan = User::factory()->create();

        $this->actingAs($orphan)->getJson('/api/customer/profile')->assertStatus(404);
    }

    public function test_an_inactivated_customer_no_longer_reaches_its_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($admin)->deleteJson("/api/admin/customers/{$customer->id}")->assertStatus(200);
        $this->postJson('/api/logout');
        $this->freshBrowserSession();

        $this->actingAs($customer->user->fresh())
            ->getJson('/api/customer/profile')
            ->assertStatus(404);
    }
}
