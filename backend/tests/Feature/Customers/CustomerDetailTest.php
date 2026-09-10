<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_detail_screen_returns_the_registration_data(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create([
            'name' => 'Imobiliária Alvorada',
            'document' => '11222333000181',
            'phone' => '(11) 98888-7777',
            'contact_name' => 'Marina Alves',
        ]);

        $response = $this->actingAs($admin)->getJson("/api/admin/customers/{$customer->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Imobiliária Alvorada');
        $response->assertJsonPath('data.document_formatted', '11.222.333/0001-81');
        $response->assertJsonPath('data.phone', '(11) 98888-7777');
        $response->assertJsonPath('data.contact_name', 'Marina Alves');
        $response->assertJsonPath('data.user.email', $customer->user->email);
    }

    public function test_a_cpf_is_formatted_for_display(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create(['document' => '52998224725']);

        $this->actingAs($admin)->getJson("/api/admin/customers/{$customer->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.document_formatted', '529.982.247-25');
    }

    public function test_the_detail_screen_lists_the_linked_tasks(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        Task::factory()->for($customer)->create(['title' => 'Enviar contrato']);
        Task::factory()->for($customer)->completed()->create(['title' => 'Ligar para o contato']);

        $response = $this->actingAs($admin)->getJson("/api/admin/customers/{$customer->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data.tasks');
        $response->assertJsonPath('data.tasks_count', 2);
        $response->assertJsonPath('data.pending_tasks_count', 1);
        $this->assertEqualsCanonicalizing(
            ['Enviar contrato', 'Ligar para o contato'],
            array_column($response->json('data.tasks'), 'title'),
        );
    }

    public function test_the_detail_screen_only_shows_that_customers_tasks(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        $other = Customer::factory()->create();
        Task::factory()->for($customer)->create(['title' => 'Tarefa do cliente']);
        Task::factory()->for($other)->create(['title' => 'Tarefa de outro cliente']);

        $response = $this->actingAs($admin)->getJson("/api/admin/customers/{$customer->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.tasks');
        $response->assertJsonPath('data.tasks.0.title', 'Tarefa do cliente');
    }

    public function test_an_unknown_customer_returns_not_found(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/api/admin/customers/9999')->assertStatus(404);
    }

    public function test_a_customer_cannot_open_another_customers_detail(): void
    {
        $customer = Customer::factory()->create();
        $other = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->getJson("/api/admin/customers/{$other->id}")
            ->assertStatus(403);
    }
}
