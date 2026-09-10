<?php

namespace Tests\Feature\Customers;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDeactivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_inactivate_a_customer(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/admin/customers/{$customer->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_inactivation_also_flags_the_status_as_inactive(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);

        $this->actingAs($admin)->deleteJson("/api/admin/customers/{$customer->id}")->assertStatus(200);

        $this->assertSame(
            CustomerStatus::Inactive,
            Customer::withTrashed()->find($customer->id)->status,
        );
    }

    public function test_the_row_is_never_physically_removed(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create(['name' => 'Histórico Ltda']);

        $this->actingAs($admin)->deleteJson("/api/admin/customers/{$customer->id}")->assertStatus(200);

        $this->assertDatabaseCount('customers', 1);
        $this->assertNotNull(Customer::withTrashed()->find($customer->id));
        $this->assertSame('Histórico Ltda', Customer::withTrashed()->find($customer->id)->name);
    }

    public function test_the_tasks_of_an_inactivated_customer_are_preserved(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        $task = Task::factory()->for($customer)->create(['title' => 'Histórico importante']);

        $this->actingAs($admin)->deleteJson("/api/admin/customers/{$customer->id}")->assertStatus(200);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Histórico importante',
            'customer_id' => $customer->id,
        ]);
    }

    public function test_an_inactivated_customer_drops_out_of_the_default_listing(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create(['name' => 'Some Sumida']);
        Customer::factory()->create(['name' => 'Continua Aqui']);

        $this->actingAs($admin)->deleteJson("/api/admin/customers/{$customer->id}")->assertStatus(200);

        $this->actingAs($admin)->getJson('/api/admin/customers')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Continua Aqui');
    }

    public function test_inactivated_customers_can_still_be_listed_on_demand(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create(['name' => 'Some Sumida']);
        Customer::factory()->create(['name' => 'Continua Aqui']);

        $this->actingAs($admin)->deleteJson("/api/admin/customers/{$customer->id}")->assertStatus(200);

        $this->actingAs($admin)->getJson('/api/admin/customers?trashed=with')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $this->actingAs($admin)->getJson('/api/admin/customers?trashed=only')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Some Sumida');
    }

    public function test_the_detail_screen_of_an_inactivated_customer_stays_reachable(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        Task::factory()->for($customer)->create(['title' => 'Histórico importante']);

        $this->actingAs($admin)->deleteJson("/api/admin/customers/{$customer->id}")->assertStatus(200);

        $this->actingAs($admin)->getJson("/api/admin/customers/{$customer->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.status', CustomerStatus::Inactive->value)
            ->assertJsonCount(1, 'data.tasks');
    }

    public function test_an_inactivated_customer_cannot_take_new_tasks(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($admin)->deleteJson("/api/admin/customers/{$customer->id}")->assertStatus(200);

        $this->actingAs($admin)->postJson('/api/admin/tasks', [
            'customer_id' => $customer->id,
            'title' => 'Nova tarefa',
            'due_date' => today()->addDay()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('customer_id');
    }

    public function test_an_inactivated_customer_can_be_reactivated(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($admin)->deleteJson("/api/admin/customers/{$customer->id}")->assertStatus(200);

        $this->actingAs($admin)->postJson("/api/admin/customers/{$customer->id}/restore")
            ->assertStatus(200)
            ->assertJsonPath('data.status', CustomerStatus::Active->value);

        $this->assertNotSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_a_customer_cannot_inactivate_customers(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->deleteJson("/api/admin/customers/{$customer->id}")
            ->assertStatus(403);

        $this->assertNotSoftDeleted('customers', ['id' => $customer->id]);
    }
}
