<?php

namespace Tests\Feature\Tickets;

use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_lists_every_ticket_newest_first(): void
    {
        $admin = User::factory()->admin()->create();

        $this->travelTo(now()->subDays(2));
        $older = Ticket::factory()->create();
        $this->travelBack();
        $newer = Ticket::factory()->create();

        $this->actingAs($admin)->getJson('/api/admin/tickets')
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.comments_count', 0)
            ->assertJsonMissingPath('data.0.comments');
    }

    public function test_the_admin_listing_filters_by_status_and_customer(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        $wanted = Ticket::factory()->for($customer)->inProgress()->create();
        Ticket::factory()->for($customer)->open()->create();
        Ticket::factory()->inProgress()->create();

        $this->actingAs($admin)->getJson("/api/admin/tickets?status=in_progress&customer_id={$customer->id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $wanted->id);

        $this->getJson('/api/admin/tickets?status=in_progress')->assertJsonCount(2, 'data');
        $this->getJson("/api/admin/tickets?customer_id={$customer->id}")->assertJsonCount(2, 'data');
        $this->getJson('/api/admin/tickets?status=inventado')->assertStatus(422);
    }

    public function test_the_admin_listing_is_paginated(): void
    {
        $admin = User::factory()->admin()->create();
        Ticket::factory()->count(3)->create();

        $this->actingAs($admin)->getJson('/api/admin/tickets?per_page=2')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_the_admin_sees_the_detail_with_comments(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->inProgress()->create();
        TicketComment::factory()->for($ticket)->create(['body' => 'Primeira resposta']);

        $this->actingAs($admin)->getJson("/api/admin/tickets/{$ticket->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $ticket->id)
            ->assertJsonPath('data.comments_count', 1)
            ->assertJsonPath('data.comments.0.body', 'Primeira resposta')
            ->assertJsonPath('data.attachments', [])
            ->assertJsonPath('data.allowed_transitions.0.status', 'waiting_customer');
    }

    public function test_the_admin_edits_title_category_description_and_priority(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->inProgress()->create(['category' => 'question']);

        $this->actingAs($admin)->putJson("/api/admin/tickets/{$ticket->id}", [
            'title' => 'Título corrigido',
            'category' => 'billing',
            'description' => 'Descrição corrigida',
            'priority' => 'high',
        ])->assertStatus(200)
            ->assertJsonPath('data.title', 'Título corrigido')
            ->assertJsonPath('data.category', 'billing')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.status', 'in_progress');

        $ticket->refresh();
        $this->assertSame('Descrição corrigida', $ticket->description);
        $this->assertSame('high', $ticket->priority->value);
    }

    public function test_editing_does_not_touch_status_customer_or_assignee(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->open()->create();
        $originalCustomer = $ticket->customer_id;

        $this->actingAs($admin)->putJson("/api/admin/tickets/{$ticket->id}", [
            'title' => 'Novo título',
            'status' => 'closed',
            'customer_id' => Customer::factory()->create()->id,
            'assigned_to' => $admin->id,
        ])->assertStatus(200);

        $ticket->refresh();
        $this->assertSame('open', $ticket->status->value);
        $this->assertSame($originalCustomer, $ticket->customer_id);
        $this->assertNull($ticket->assigned_to);
    }

    public function test_editing_rejects_an_empty_title_and_an_invalid_priority(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($admin)->putJson("/api/admin/tickets/{$ticket->id}", [
            'title' => '',
            'priority' => 'critical',
        ])->assertStatus(422)
            ->assertJsonPath('errors.title.0', 'O campo título é obrigatório.')
            ->assertJsonPath('errors.priority.0', 'O prioridade selecionado é inválido.');
    }

    public function test_the_admin_assigns_another_admin_without_changing_the_status(): void
    {
        $admin = User::factory()->admin()->create();
        $ana = User::factory()->admin()->create(['name' => 'Ana']);
        $ticket = Ticket::factory()->waitingCustomer()->create();

        $this->actingAs($admin)->putJson("/api/admin/tickets/{$ticket->id}/assignee", [
            'assigned_to' => $ana->id,
        ])->assertStatus(200)
            ->assertJsonPath('data.assignee', ['id' => $ana->id, 'name' => 'Ana'])
            ->assertJsonPath('data.status', 'waiting_customer');

        $this->assertSame($ana->id, $ticket->fresh()->assigned_to);
        $this->assertSame('waiting_customer', $ticket->fresh()->status->value);
    }

    public function test_null_removes_the_assignee(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->assignedTo($admin)->inProgress()->create();

        $this->actingAs($admin)->putJson("/api/admin/tickets/{$ticket->id}/assignee", [
            'assigned_to' => null,
        ])->assertStatus(200)
            ->assertJsonPath('data.assignee', null)
            ->assertJsonPath('data.status', 'in_progress');

        $this->assertNull($ticket->fresh()->assigned_to);
    }

    public function test_assigning_a_customer_user_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        $ticket = Ticket::factory()->for($customer)->create();

        $this->actingAs($admin)->putJson("/api/admin/tickets/{$ticket->id}/assignee", [
            'assigned_to' => $customer->user_id,
        ])->assertStatus(422)
            ->assertJsonPath('errors.assigned_to.0', 'O responsável selecionado é inválido.');

        $this->assertNull($ticket->fresh()->assigned_to);
    }

    public function test_assigning_an_unknown_user_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($admin)->putJson("/api/admin/tickets/{$ticket->id}/assignee", [
            'assigned_to' => 999999,
        ])->assertStatus(422)->assertJsonValidationErrors('assigned_to');
    }

    public function test_the_assignee_field_must_be_sent(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->assignedTo($admin)->create();

        $this->actingAs($admin)->putJson("/api/admin/tickets/{$ticket->id}/assignee", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('assigned_to');

        $this->assertSame($admin->id, $ticket->fresh()->assigned_to);
    }

    public function test_staff_lists_only_admins_with_id_and_name(): void
    {
        $bruno = User::factory()->admin()->create(['name' => 'Bruno']);
        $ana = User::factory()->admin()->create(['name' => 'Ana']);
        Customer::factory()->create();

        $this->actingAs($bruno)->getJson('/api/admin/staff')
            ->assertStatus(200)
            ->assertExactJson(['data' => [
                ['id' => $ana->id, 'name' => 'Ana'],
                ['id' => $bruno->id, 'name' => 'Bruno'],
            ]]);
    }

    public function test_a_customer_cannot_reach_the_admin_ticket_routes(): void
    {
        $customer = Customer::factory()->create();
        $ticket = Ticket::factory()->for($customer)->openedByCustomer()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($customer->user);

        $this->getJson('/api/admin/tickets')->assertStatus(403);
        $this->postJson('/api/admin/tickets', [
            'customer_id' => $customer->id,
            'title' => 'Tentativa',
            'category' => 'other',
            'description' => '...',
            'priority' => 'urgent',
        ])->assertStatus(403);
        $this->getJson("/api/admin/tickets/{$ticket->id}")->assertStatus(403);
        $this->putJson("/api/admin/tickets/{$ticket->id}", ['priority' => 'urgent'])->assertStatus(403);
        $this->putJson("/api/admin/tickets/{$ticket->id}/assignee", ['assigned_to' => $admin->id])->assertStatus(403);
        $this->getJson('/api/admin/staff')->assertStatus(403);

        $ticket->refresh();
        $this->assertSame('medium', $ticket->priority->value);
        $this->assertNull($ticket->assigned_to);
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_guests_are_rejected(): void
    {
        $this->getJson('/api/admin/tickets')->assertStatus(401);
        $this->getJson('/api/customer/tickets')->assertStatus(401);
        $this->postJson('/api/customer/tickets', [])->assertStatus(401);
    }
}
