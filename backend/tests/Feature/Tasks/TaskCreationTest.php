<?php

namespace Tests\Feature\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Customer;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_create_a_task_linked_to_a_customer(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        $dueDate = today()->addDays(7)->toDateString();

        $response = $this->actingAs($admin)->postJson('/api/admin/tasks', [
            'customer_id' => $customer->id,
            'title' => 'Enviar proposta comercial',
            'description' => 'Montar a proposta com as condições combinadas na reunião.',
            'due_date' => $dueDate,
            'priority' => TaskPriority::High->value,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.title', 'Enviar proposta comercial');
        $response->assertJsonPath('data.description', 'Montar a proposta com as condições combinadas na reunião.');
        $response->assertJsonPath('data.due_date', $dueDate);
        $response->assertJsonPath('data.priority', TaskPriority::High->value);
        $response->assertJsonPath('data.priority_label', 'Alta');
        $response->assertJsonPath('data.status', TaskStatus::Pending->value);
        $response->assertJsonPath('data.customer_id', $customer->id);

        $this->assertDatabaseHas('tasks', [
            'customer_id' => $customer->id,
            'title' => 'Enviar proposta comercial',
            'status' => TaskStatus::Pending->value,
        ]);
    }

    public function test_a_new_task_starts_pending_and_uncompleted(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/admin/tasks', [
            'customer_id' => $customer->id,
            'title' => 'Ligar para o contato',
            'due_date' => today()->addDay()->toDateString(),
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', TaskStatus::Pending->value);
        $response->assertJsonPath('data.completed_at', null);
    }

    public function test_the_task_records_who_created_it(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($admin)->postJson('/api/admin/tasks', [
            'customer_id' => $customer->id,
            'title' => 'Ligar para o contato',
            'due_date' => today()->addDay()->toDateString(),
        ])->assertStatus(201);

        $this->assertDatabaseHas('tasks', ['created_by' => $admin->id]);
    }

    public function test_the_description_is_optional(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($admin)->postJson('/api/admin/tasks', [
            'customer_id' => $customer->id,
            'title' => 'Sem descrição',
            'due_date' => today()->addDay()->toDateString(),
        ])->assertStatus(201)->assertJsonPath('data.description', null);
    }

    public function test_the_priority_defaults_to_medium(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($admin)->postJson('/api/admin/tasks', [
            'customer_id' => $customer->id,
            'title' => 'Sem prioridade informada',
            'due_date' => today()->addDay()->toDateString(),
        ])->assertStatus(201)->assertJsonPath('data.priority', TaskPriority::Medium->value);
    }

    public function test_every_priority_level_is_accepted(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $expectedLabels = [
            TaskPriority::High->value => 'Alta',
            TaskPriority::Medium->value => 'Média',
            TaskPriority::Low->value => 'Baixa',
        ];

        foreach ($expectedLabels as $priority => $label) {
            $this->actingAs($admin)->postJson('/api/admin/tasks', [
                'customer_id' => $customer->id,
                'title' => 'Tarefa '.$priority,
                'due_date' => today()->addDay()->toDateString(),
                'priority' => $priority,
            ])->assertStatus(201)
                ->assertJsonPath('data.priority', $priority)
                ->assertJsonPath('data.priority_label', $label);
        }
    }

    public function test_an_unsupported_priority_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($admin)->postJson('/api/admin/tasks', [
            'customer_id' => $customer->id,
            'title' => 'Prioridade inválida',
            'due_date' => today()->addDay()->toDateString(),
            'priority' => 'urgentíssima',
        ])->assertStatus(422)->assertJsonValidationErrors('priority');
    }

    public function test_a_task_requires_a_title_a_deadline_and_a_customer(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/admin/tasks', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id', 'title', 'due_date']);
    }

    public function test_the_deadline_must_be_a_date(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($admin)->postJson('/api/admin/tasks', [
            'customer_id' => $customer->id,
            'title' => 'Prazo inválido',
            'due_date' => 'quinta-feira',
        ])->assertStatus(422)->assertJsonValidationErrors('due_date');
    }

    public function test_a_task_cannot_be_created_for_an_unknown_customer(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/admin/tasks', [
            'customer_id' => 9999,
            'title' => 'Cliente inexistente',
            'due_date' => today()->addDay()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('customer_id');
    }

    public function test_an_admin_can_edit_a_task(): void
    {
        $admin = User::factory()->admin()->create();
        $task = Task::factory()->create(['title' => 'Título antigo']);
        $newDate = today()->addDays(10)->toDateString();

        $this->actingAs($admin)->putJson("/api/admin/tasks/{$task->id}", [
            'title' => 'Título novo',
            'due_date' => $newDate,
            'priority' => TaskPriority::Low->value,
        ])->assertStatus(200)
            ->assertJsonPath('data.title', 'Título novo')
            ->assertJsonPath('data.due_date', $newDate)
            ->assertJsonPath('data.priority', TaskPriority::Low->value);
    }

    public function test_an_admin_can_delete_a_task(): void
    {
        $admin = User::factory()->admin()->create();
        $task = Task::factory()->create();

        $this->actingAs($admin)->deleteJson("/api/admin/tasks/{$task->id}")->assertStatus(200);

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_a_customer_cannot_manage_tasks(): void
    {
        $customer = Customer::factory()->create();
        $task = Task::factory()->for($customer)->create();

        $this->actingAs($customer->user)->postJson('/api/admin/tasks', [
            'customer_id' => $customer->id,
            'title' => 'Tarefa criada pelo cliente',
            'due_date' => today()->addDay()->toDateString(),
        ])->assertStatus(403);

        $this->actingAs($customer->user)
            ->putJson("/api/admin/tasks/{$task->id}", ['title' => 'Editado'])
            ->assertStatus(403);

        $this->actingAs($customer->user)
            ->deleteJson("/api/admin/tasks/{$task->id}")
            ->assertStatus(403);
    }

    public function test_guests_cannot_create_tasks(): void
    {
        $this->postJson('/api/admin/tasks', [])->assertStatus(401);
    }
}
