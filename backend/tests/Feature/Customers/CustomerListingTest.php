<?php

namespace Tests\Feature\Customers;

use App\Enums\CustomerSegment;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_sees_the_customer_list(): void
    {
        $admin = User::factory()->admin()->create();
        Customer::factory()->count(3)->create();

        $response = $this->actingAs($admin)->getJson('/api/admin/customers');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure([
            'data' => [['id', 'name', 'document', 'document_formatted', 'email', 'segment', 'status', 'tasks_count']],
            'meta' => ['current_page', 'total'],
        ]);
    }

    public function test_the_list_can_be_searched_by_name(): void
    {
        $admin = User::factory()->admin()->create();
        Customer::factory()->create(['name' => 'Imobiliária Alvorada']);
        Customer::factory()->create(['name' => 'Corban Prata']);

        $response = $this->actingAs($admin)->getJson('/api/admin/customers?search=Alvorada');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Imobiliária Alvorada');
    }

    public function test_the_search_by_name_is_a_partial_match(): void
    {
        $admin = User::factory()->admin()->create();
        Customer::factory()->create(['name' => 'Construtora Horizonte']);
        Customer::factory()->create(['name' => 'Corban Prata']);

        $this->actingAs($admin)->getJson('/api/admin/customers?search=Cor')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Corban Prata');
    }

    public function test_the_list_can_be_searched_by_document(): void
    {
        $admin = User::factory()->admin()->create();
        Customer::factory()->create(['name' => 'Alvo', 'document' => '11222333000181']);
        Customer::factory()->create(['name' => 'Outro', 'document' => '11444777000161']);

        $this->actingAs($admin)->getJson('/api/admin/customers?search=11222333000181')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alvo');
    }

    public function test_the_search_by_document_ignores_punctuation(): void
    {
        $admin = User::factory()->admin()->create();
        Customer::factory()->create(['name' => 'Alvo', 'document' => '11222333000181']);
        Customer::factory()->create(['name' => 'Outro', 'document' => '11444777000161']);

        $this->actingAs($admin)->getJson('/api/admin/customers?search=11.222.333/0001-81')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alvo');
    }

    public function test_a_search_with_no_match_returns_an_empty_list(): void
    {
        $admin = User::factory()->admin()->create();
        Customer::factory()->count(2)->create();

        $this->actingAs($admin)->getJson('/api/admin/customers?search=inexistente')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_the_list_can_be_filtered_by_status(): void
    {
        $admin = User::factory()->admin()->create();
        Customer::factory()->create(['name' => 'Ativa Ltda', 'status' => CustomerStatus::Active]);
        Customer::factory()->inactive()->create(['name' => 'Inativa Ltda']);

        $this->actingAs($admin)->getJson('/api/admin/customers?status=active')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ativa Ltda');

        $this->actingAs($admin)->getJson('/api/admin/customers?status=inactive')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Inativa Ltda');
    }

    public function test_the_list_can_be_filtered_by_segment(): void
    {
        $admin = User::factory()->admin()->create();
        Customer::factory()->segment(CustomerSegment::Corban)->create(['name' => 'Corban Prata']);
        Customer::factory()->segment(CustomerSegment::RealEstate)->create(['name' => 'Imobiliária Alvorada']);

        $this->actingAs($admin)->getJson('/api/admin/customers?segment=corban')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Corban Prata');
    }

    public function test_search_and_status_filters_combine(): void
    {
        $admin = User::factory()->admin()->create();
        Customer::factory()->create(['name' => 'Alvorada Ativa', 'status' => CustomerStatus::Active]);
        Customer::factory()->inactive()->create(['name' => 'Alvorada Inativa']);
        Customer::factory()->create(['name' => 'Outra Empresa', 'status' => CustomerStatus::Active]);

        $this->actingAs($admin)->getJson('/api/admin/customers?search=Alvorada&status=active')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alvorada Ativa');
    }

    public function test_an_invalid_status_filter_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/api/admin/customers?status=arquivado')
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_the_list_carries_the_pending_task_count_per_customer(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        Task::factory()->for($customer)->count(2)->create();
        Task::factory()->for($customer)->completed()->create();

        $this->actingAs($admin)->getJson('/api/admin/customers')
            ->assertStatus(200)
            ->assertJsonPath('data.0.tasks_count', 3)
            ->assertJsonPath('data.0.pending_tasks_count', 2);
    }

    public function test_a_customer_cannot_list_customers(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)->getJson('/api/admin/customers')->assertStatus(403);
    }

    public function test_guests_cannot_list_customers(): void
    {
        $this->getJson('/api/admin/customers')->assertStatus(401);
    }
}
