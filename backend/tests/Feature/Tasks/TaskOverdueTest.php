<?php

namespace Tests\Feature\Tasks;

use App\Models\Customer;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TaskOverdueTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_pending_task_past_its_deadline_is_flagged_as_overdue(): void
    {
        $admin = User::factory()->admin()->create();
        $task = Task::factory()->dueOn(today()->subDay()->toDateString())->create();

        $this->actingAs($admin)->getJson("/api/admin/tasks/{$task->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.is_overdue', true);
    }

    public function test_a_task_due_today_is_not_overdue_yet(): void
    {
        $admin = User::factory()->admin()->create();
        $task = Task::factory()->dueOn(today()->toDateString())->create();

        $this->actingAs($admin)->getJson("/api/admin/tasks/{$task->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.is_overdue', false);
    }

    public function test_a_task_due_in_the_future_is_not_overdue(): void
    {
        $admin = User::factory()->admin()->create();
        $task = Task::factory()->dueOn(today()->addDay()->toDateString())->create();

        $this->actingAs($admin)->getJson("/api/admin/tasks/{$task->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.is_overdue', false);
    }

    public function test_a_completed_task_is_never_overdue_even_past_its_deadline(): void
    {
        $admin = User::factory()->admin()->create();
        $task = Task::factory()->completed()->dueOn(today()->subDays(10)->toDateString())->create();

        $this->actingAs($admin)->getJson("/api/admin/tasks/{$task->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.is_overdue', false);
    }

    public function test_a_task_becomes_overdue_as_the_clock_passes_the_deadline_with_no_job_running(): void
    {
        $admin = User::factory()->admin()->create();

        Carbon::setTestNow('2026-09-08 08:00:00');
        $task = Task::factory()->dueOn('2026-09-08')->create();

        $this->actingAs($admin)->getJson("/api/admin/tasks/{$task->id}")
            ->assertJsonPath('data.is_overdue', false);

        Carbon::setTestNow('2026-09-09 08:00:00');

        $this->actingAs($admin)->getJson("/api/admin/tasks/{$task->id}")
            ->assertJsonPath('data.is_overdue', true);
    }

    public function test_a_task_due_today_is_not_overdue_late_in_the_brazilian_evening(): void
    {
        $admin = User::factory()->admin()->create();

        Carbon::setTestNow(Carbon::parse('2026-09-10 02:00:00', 'UTC'));

        $this->assertSame('2026-09-09', today()->toDateString());

        $task = Task::factory()->dueOn('2026-09-09')->create();

        $this->actingAs($admin)->getJson("/api/admin/tasks/{$task->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.is_overdue', false);

        $this->actingAs($admin)->getJson('/api/admin/tasks?overdue=1')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_the_application_runs_on_the_brazilian_business_timezone(): void
    {
        $this->assertSame('America/Sao_Paulo', config('app.timezone'));
    }

    public function test_the_list_can_be_filtered_to_overdue_tasks_only(): void
    {
        $admin = User::factory()->admin()->create();
        Task::factory()->overdue()->create(['title' => 'Atrasada']);
        Task::factory()->dueOn(today()->addDays(5)->toDateString())->create(['title' => 'Em dia']);
        Task::factory()->completed()->dueOn(today()->subDays(5)->toDateString())->create(['title' => 'Concluída atrasada']);

        $response = $this->actingAs($admin)->getJson('/api/admin/tasks?overdue=1');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Atrasada');
    }

    public function test_the_overdue_flag_travels_with_the_customer_detail_screen(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        Task::factory()->for($customer)->overdue()->create(['title' => 'Atrasada']);
        Task::factory()->for($customer)->dueOn(today()->addDays(3)->toDateString())->create(['title' => 'Em dia']);

        $response = $this->actingAs($admin)->getJson("/api/admin/customers/{$customer->id}");

        $response->assertStatus(200);

        $tasks = collect($response->json('data.tasks'))->keyBy('title');

        $this->assertTrue($tasks['Atrasada']['is_overdue']);
        $this->assertFalse($tasks['Em dia']['is_overdue']);
    }
}
