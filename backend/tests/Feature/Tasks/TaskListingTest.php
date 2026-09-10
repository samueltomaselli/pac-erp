<?php

namespace Tests\Feature\Tasks;

use App\Enums\TaskPriority;
use App\Models\Customer;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_sees_the_task_list(): void
    {
        $admin = User::factory()->admin()->create();
        Task::factory()->count(3)->create();

        $response = $this->actingAs($admin)->getJson('/api/admin/tasks');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure([
            'data' => [['id', 'title', 'due_date', 'priority', 'status', 'is_overdue', 'customer']],
            'meta' => ['current_page', 'total'],
        ]);
    }

    public function test_the_list_can_be_filtered_to_pending_tasks(): void
    {
        $admin = User::factory()->admin()->create();
        Task::factory()->create(['title' => 'Pendente']);
        Task::factory()->completed()->create(['title' => 'Concluída']);

        $this->actingAs($admin)->getJson('/api/admin/tasks?status=pending')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Pendente');
    }

    public function test_the_list_can_be_filtered_to_completed_tasks(): void
    {
        $admin = User::factory()->admin()->create();
        Task::factory()->create(['title' => 'Pendente']);
        Task::factory()->completed()->create(['title' => 'Concluída']);

        $this->actingAs($admin)->getJson('/api/admin/tasks?status=completed')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Concluída');
    }

    public function test_the_list_can_be_filtered_by_customer(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        $other = Customer::factory()->create();
        Task::factory()->for($customer)->create(['title' => 'Do cliente']);
        Task::factory()->for($other)->count(2)->create();

        $this->actingAs($admin)->getJson("/api/admin/tasks?customer_id={$customer->id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Do cliente')
            ->assertJsonPath('data.0.customer.name', $customer->name);
    }

    public function test_the_list_can_be_filtered_by_a_deadline_window(): void
    {
        $admin = User::factory()->admin()->create();
        Task::factory()->dueOn('2026-09-05')->create(['title' => 'Antes da janela']);
        Task::factory()->dueOn('2026-09-10')->create(['title' => 'Dentro da janela']);
        Task::factory()->dueOn('2026-09-20')->create(['title' => 'Depois da janela']);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/tasks?due_from=2026-09-08&due_until=2026-09-15');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Dentro da janela');
    }

    public function test_the_deadline_window_boundaries_are_inclusive(): void
    {
        $admin = User::factory()->admin()->create();
        Task::factory()->dueOn('2026-09-08')->create(['title' => 'Primeiro dia']);
        Task::factory()->dueOn('2026-09-15')->create(['title' => 'Último dia']);

        $this->actingAs($admin)
            ->getJson('/api/admin/tasks?due_from=2026-09-08&due_until=2026-09-15')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_the_list_can_be_filtered_by_priority(): void
    {
        $admin = User::factory()->admin()->create();
        Task::factory()->priority(TaskPriority::High)->create(['title' => 'Urgente']);
        Task::factory()->priority(TaskPriority::Low)->create(['title' => 'Pode esperar']);

        $this->actingAs($admin)->getJson('/api/admin/tasks?priority=high')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Urgente');
    }

    public function test_the_customer_and_status_filters_combine(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        Task::factory()->for($customer)->create(['title' => 'Pendente do cliente']);
        Task::factory()->for($customer)->completed()->create(['title' => 'Concluída do cliente']);
        Task::factory()->create(['title' => 'De outro cliente']);

        $this->actingAs($admin)
            ->getJson("/api/admin/tasks?customer_id={$customer->id}&status=pending")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Pendente do cliente');
    }

    public function test_the_list_is_ordered_by_the_nearest_deadline(): void
    {
        $admin = User::factory()->admin()->create();
        Task::factory()->dueOn('2026-09-20')->create(['title' => 'Terceira']);
        Task::factory()->dueOn('2026-09-09')->create(['title' => 'Primeira']);
        Task::factory()->dueOn('2026-09-12')->create(['title' => 'Segunda']);

        $response = $this->actingAs($admin)->getJson('/api/admin/tasks');

        $response->assertStatus(200);
        $this->assertSame(
            ['Primeira', 'Segunda', 'Terceira'],
            array_column($response->json('data'), 'title'),
        );
    }

    public function test_an_invalid_status_filter_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/api/admin/tasks?status=arquivada')
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_a_customer_cannot_list_tasks(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)->getJson('/api/admin/tasks')->assertStatus(403);
    }

    public function test_guests_cannot_list_tasks(): void
    {
        $this->getJson('/api/admin/tasks')->assertStatus(401);
    }
}
