<?php

namespace Tests\Feature\Tasks;

use App\Enums\TaskStatus;
use App\Models\Customer;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TaskCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_mark_a_task_as_completed(): void
    {
        $admin = User::factory()->admin()->create();
        $task = Task::factory()->create();

        $response = $this->actingAs($admin)->postJson("/api/admin/tasks/{$task->id}/complete");

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', TaskStatus::Completed->value);
        $response->assertJsonPath('data.status_label', 'Concluída');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Completed->value,
        ]);
    }

    public function test_completing_records_the_date_and_time(): void
    {
        Carbon::setTestNow('2026-09-08 14:35:07');

        $admin = User::factory()->admin()->create();
        $task = Task::factory()->create();

        $this->assertNull($task->completed_at);

        $response = $this->actingAs($admin)->postJson("/api/admin/tasks/{$task->id}/complete");

        $response->assertStatus(200);

        $completedAt = $task->fresh()->completed_at;

        $this->assertNotNull($completedAt);
        $this->assertSame('2026-09-08 14:35:07', $completedAt->format('Y-m-d H:i:s'));
        $this->assertNotNull($response->json('data.completed_at'));

        Carbon::setTestNow();
    }

    public function test_completing_an_already_completed_task_keeps_the_original_timestamp(): void
    {
        $admin = User::factory()->admin()->create();
        $task = Task::factory()->completed()->create(['completed_at' => '2026-09-01 09:00:00']);

        $response = $this->actingAs($admin)->postJson("/api/admin/tasks/{$task->id}/complete");

        $response->assertStatus(422);
        $this->assertSame('2026-09-01 09:00:00', $task->fresh()->completed_at->format('Y-m-d H:i:s'));
    }

    public function test_a_completed_task_can_be_reopened_and_loses_its_timestamp(): void
    {
        $admin = User::factory()->admin()->create();
        $task = Task::factory()->completed()->create();

        $this->actingAs($admin)->postJson("/api/admin/tasks/{$task->id}/reopen")
            ->assertStatus(200)
            ->assertJsonPath('data.status', TaskStatus::Pending->value)
            ->assertJsonPath('data.completed_at', null);

        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_reopening_a_pending_task_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $task = Task::factory()->create();

        $this->actingAs($admin)->postJson("/api/admin/tasks/{$task->id}/reopen")->assertStatus(422);
    }

    public function test_completing_an_overdue_task_clears_the_overdue_flag(): void
    {
        $admin = User::factory()->admin()->create();
        $task = Task::factory()->overdue()->create();

        $this->actingAs($admin)->getJson("/api/admin/tasks/{$task->id}")
            ->assertJsonPath('data.is_overdue', true);

        $this->actingAs($admin)->postJson("/api/admin/tasks/{$task->id}/complete")
            ->assertStatus(200)
            ->assertJsonPath('data.is_overdue', false);
    }

    public function test_a_customer_cannot_complete_tasks(): void
    {
        $customer = Customer::factory()->create();
        $task = Task::factory()->for($customer)->create();

        $this->actingAs($customer->user)
            ->postJson("/api/admin/tasks/{$task->id}/complete")
            ->assertStatus(403);

        $this->assertSame(TaskStatus::Pending, $task->fresh()->status);
    }
}
