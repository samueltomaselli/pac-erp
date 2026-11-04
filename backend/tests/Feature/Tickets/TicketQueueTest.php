<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketPriority;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_lists_every_ticket_newest_first_by_default(): void
    {
        $admin = User::factory()->admin()->create();
        $older = Ticket::factory()->create(['created_at' => now()->subDay()]);
        $newer = Ticket::factory()->create();

        $this->actingAs($admin)->getJson('/api/admin/tickets')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_the_queue_filters_by_priority_and_assignee(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $assigned = Ticket::factory()->assignedTo($admin)->priority(TicketPriority::Urgent)->create();
        Ticket::factory()->assignedTo($otherAdmin)->priority(TicketPriority::Urgent)->create();
        Ticket::factory()->priority(TicketPriority::High)->create();
        $unassigned = Ticket::factory()->priority(TicketPriority::Urgent)->create();

        $this->actingAs($admin)->getJson('/api/admin/tickets?priority=urgent')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->getJson("/api/admin/tickets?assigned_to={$admin->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assigned->id);

        $this->getJson('/api/admin/tickets?assigned_to=none')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $unassigned->id]);
    }

    public function test_assignee_filter_with_non_admin_id_returns_no_tickets(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        Ticket::factory()->for($customer)->assignedTo($admin)->create();

        $this->actingAs($admin)->getJson("/api/admin/tickets?assigned_to={$customer->user_id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_active_filter_excludes_resolved_and_closed_tickets(): void
    {
        $admin = User::factory()->admin()->create();
        Ticket::factory()->open()->create();
        Ticket::factory()->inProgress()->create();
        Ticket::factory()->waitingCustomer()->create();
        Ticket::factory()->resolved()->create();
        Ticket::factory()->closed()->create();

        $this->actingAs($admin)->getJson('/api/admin/tickets?active=1')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_status_takes_precedence_over_the_active_filter(): void
    {
        $admin = User::factory()->admin()->create();
        $resolved = Ticket::factory()->resolved()->create();
        Ticket::factory()->open()->create();

        $this->actingAs($admin)->getJson('/api/admin/tickets?active=1&status=resolved')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $resolved->id);
    }

    public function test_search_matches_title_and_ticket_reference_formats(): void
    {
        $admin = User::factory()->admin()->create();
        $id = 12;
        Ticket::factory()->create(['id' => $id, 'title' => 'Erro ao emitir boleto']);

        $this->actingAs($admin)->getJson('/api/admin/tickets?q=emitir%20boleto')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);

        foreach (['CH-12', '#CH-12', '12'] as $term) {
            $this->getJson('/api/admin/tickets?q='.urlencode($term))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $id);
        }
    }

    public function test_combined_filters_intersect(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        $matching = Ticket::factory()->for($customer)->assignedTo($admin)->priority(TicketPriority::Urgent)->create();
        Ticket::factory()->for($customer)->assignedTo($otherAdmin)->priority(TicketPriority::Urgent)->create();
        Ticket::factory()->for($customer)->assignedTo($admin)->priority(TicketPriority::High)->create();
        Ticket::factory()->assignedTo($admin)->priority(TicketPriority::Urgent)->create();

        $this->actingAs($admin)->getJson("/api/admin/tickets?customer_id={$customer->id}&priority=urgent&assigned_to={$admin->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id);
    }

    public function test_priority_sort_orders_by_priority_then_oldest_ticket(): void
    {
        $admin = User::factory()->admin()->create();
        $oldUrgent = Ticket::factory()->priority(TicketPriority::Urgent)->create(['created_at' => now()->subDays(3)]);
        $sameCreatedAt = now()->subDays(2);
        $tieUrgentOlderId = Ticket::factory()->priority(TicketPriority::Urgent)->create(['created_at' => $sameCreatedAt]);
        $tieUrgentNewerId = Ticket::factory()->priority(TicketPriority::Urgent)->create(['created_at' => $sameCreatedAt]);
        Ticket::factory()->priority(TicketPriority::Low)->create(['created_at' => now()->subDays(2)]);
        Ticket::factory()->priority(TicketPriority::Medium)->create(['created_at' => now()->subDay()]);
        Ticket::factory()->priority(TicketPriority::High)->create();
        $newUrgent = Ticket::factory()->priority(TicketPriority::Urgent)->create();

        $this->actingAs($admin)->getJson('/api/admin/tickets?sort=priority')
            ->assertOk()
            ->assertJsonPath('data.0.id', $oldUrgent->id)
            ->assertJsonPath('data.1.id', $tieUrgentNewerId->id)
            ->assertJsonPath('data.2.id', $tieUrgentOlderId->id)
            ->assertJsonPath('data.3.id', $newUrgent->id)
            ->assertJsonPath('data.4.priority', 'high')
            ->assertJsonPath('data.5.priority', 'medium')
            ->assertJsonPath('data.6.priority', 'low');
    }

    public function test_updated_sort_places_the_most_recently_commented_ticket_first(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->create();
        $otherTicket = Ticket::factory()->create();
        $commenter = User::factory()->admin()->create();

        $this->travelTo(now()->addMinute());
        TicketComment::factory()->for($ticket)->by($commenter)->create();

        $this->actingAs($admin)->getJson('/api/admin/tickets?sort=updated')
            ->assertOk()
            ->assertJsonPath('data.0.id', $ticket->id)
            ->assertJsonPath('data.1.id', $otherTicket->id);
    }

    public function test_invalid_priority_and_sort_return_portuguese_validation_errors(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->getJson('/api/admin/tickets?priority=altissima')
            ->assertUnprocessable()
            ->assertJsonPath('errors.priority.0', 'A prioridade selecionada é inválida.');

        $this->getJson('/api/admin/tickets?sort=qualquer')
            ->assertUnprocessable()
            ->assertJsonPath('errors.sort.0', 'A ordenação selecionada é inválida.');
    }

    public function test_pagination_total_reflects_the_applied_filters(): void
    {
        $admin = User::factory()->admin()->create();
        Ticket::factory()->count(3)->priority(TicketPriority::Urgent)->create();
        Ticket::factory()->count(2)->priority(TicketPriority::Low)->create();

        $this->actingAs($admin)->getJson('/api/admin/tickets?priority=urgent&per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_customer_cannot_reach_the_admin_queue_with_filters(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer->user)
            ->getJson('/api/admin/tickets?active=1&priority=urgent&assigned_to=none&q=CH-12&sort=priority&customer_id='.$customer->id)
            ->assertForbidden();
    }
}
