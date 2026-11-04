<?php

namespace Tests\Feature\Customers;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerTicketsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-03 10:00:00'));
        $this->admin = User::factory()->admin()->create();
    }

    public function test_the_detail_lists_the_customers_tickets_most_recently_updated_first(): void
    {
        $customer = Customer::factory()->create();

        $older = Ticket::factory()->for($customer)->create(['title' => 'Mais antigo']);
        $this->travelTo(Carbon::parse('2026-11-03 12:00:00'));
        $newer = Ticket::factory()->for($customer)->create(['title' => 'Mais novo']);
        $this->travelTo(Carbon::parse('2026-11-03 11:00:00'));
        $middle = Ticket::factory()->for($customer)->create(['title' => 'Do meio']);

        $response = $this->actingAs($this->admin)->getJson("/api/admin/customers/{$customer->id}");

        $response->assertStatus(200);
        $this->assertSame(
            [$newer->id, $middle->id, $older->id],
            array_column($response->json('data.tickets'), 'id'),
        );
    }

    public function test_tickets_updated_at_the_same_time_are_ordered_by_id_descending(): void
    {
        $customer = Customer::factory()->create();
        $first = Ticket::factory()->for($customer)->create();
        $second = Ticket::factory()->for($customer)->create();

        $response = $this->actingAs($this->admin)->getJson("/api/admin/customers/{$customer->id}");

        $this->assertSame([$second->id, $first->id], array_column($response->json('data.tickets'), 'id'));
    }

    public function test_a_new_comment_moves_the_ticket_to_the_top(): void
    {
        $customer = Customer::factory()->create();
        $commented = Ticket::factory()->for($customer)->create();
        $this->travelTo(Carbon::parse('2026-11-03 11:00:00'));
        $untouched = Ticket::factory()->for($customer)->create();

        $this->travelTo(Carbon::parse('2026-11-03 15:00:00'));
        TicketComment::factory()->for($commented)->create();

        $response = $this->actingAs($this->admin)->getJson("/api/admin/customers/{$customer->id}");

        $this->assertSame([$commented->id, $untouched->id], array_column($response->json('data.tickets'), 'id'));
    }

    public function test_tickets_from_another_customer_are_not_shown(): void
    {
        $customer = Customer::factory()->create();
        $other = Customer::factory()->create();
        Ticket::factory()->for($customer)->create(['title' => 'Chamado do cliente']);
        Ticket::factory()->for($other)->count(2)->create();

        $response = $this->actingAs($this->admin)->getJson("/api/admin/customers/{$customer->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.tickets');
        $response->assertJsonPath('data.tickets.0.title', 'Chamado do cliente');
        $response->assertJsonPath('data.tickets_count', 1);
        $response->assertJsonPath('data.open_tickets_count', 1);
    }

    public function test_the_counters_separate_all_tickets_from_the_ones_in_progress(): void
    {
        $customer = Customer::factory()->create();

        foreach (TicketStatus::cases() as $status) {
            Ticket::factory()->for($customer)->status($status)->create();
        }

        $response = $this->actingAs($this->admin)->getJson("/api/admin/customers/{$customer->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data.tickets');
        $response->assertJsonPath('data.tickets_count', 5);
        $response->assertJsonPath('data.open_tickets_count', 3);
    }

    public function test_each_ticket_carries_the_list_fields(): void
    {
        $customer = Customer::factory()->create();
        $agent = User::factory()->admin()->create(['name' => 'Ana']);
        $ticket = Ticket::factory()
            ->for($customer)
            ->assignedTo($agent)
            ->priority(TicketPriority::Urgent)
            ->resolved()
            ->create(['title' => 'Erro ao emitir boleto']);
        TicketComment::factory()->for($ticket)->count(3)->create();

        $response = $this->actingAs($this->admin)->getJson("/api/admin/customers/{$customer->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.tickets.0.id', $ticket->id);
        $response->assertJsonPath('data.tickets.0.reference', "#CH-{$ticket->id}");
        $response->assertJsonPath('data.tickets.0.title', 'Erro ao emitir boleto');
        $response->assertJsonPath('data.tickets.0.status', 'resolved');
        $response->assertJsonPath('data.tickets.0.status_label', 'Resolvido');
        $response->assertJsonPath('data.tickets.0.priority', 'urgent');
        $response->assertJsonPath('data.tickets.0.priority_label', 'Urgente');
        $response->assertJsonPath('data.tickets.0.assignee', ['id' => $agent->id, 'name' => 'Ana']);
        $response->assertJsonPath('data.tickets.0.comments_count', 3);
        $response->assertJsonPath('data.tickets.0.opened_at', $ticket->created_at->toIso8601String());
        $response->assertJsonPath('data.tickets.0.updated_at', $ticket->fresh()->updated_at->toIso8601String());
        $response->assertJsonPath('data.tickets.0.resolved_at', $ticket->resolved_at->toIso8601String());
        $response->assertJsonPath('data.tickets.0.closed_at', null);
    }

    public function test_a_ticket_without_assignee_returns_null(): void
    {
        $customer = Customer::factory()->create();
        Ticket::factory()->for($customer)->create();

        $response = $this->actingAs($this->admin)->getJson("/api/admin/customers/{$customer->id}");

        $this->assertArrayHasKey('assignee', $response->json('data.tickets.0'));
        $response->assertJsonPath('data.tickets.0.assignee', null);
        $response->assertJsonPath('data.tickets.0.comments_count', 0);
    }

    public function test_the_tickets_in_the_detail_do_not_carry_comments_or_attachments(): void
    {
        $customer = Customer::factory()->create();
        $ticket = Ticket::factory()->for($customer)->create();
        $comment = TicketComment::factory()->for($ticket)->create();
        TicketAttachment::factory()->forComment($comment)->create();
        TicketAttachment::factory()->for($ticket)->create();

        $response = $this->actingAs($this->admin)->getJson("/api/admin/customers/{$customer->id}");

        $response->assertStatus(200);
        $item = $response->json('data.tickets.0');
        $this->assertArrayNotHasKey('comments', $item);
        $this->assertArrayNotHasKey('attachments', $item);
        $this->assertArrayNotHasKey('customer', $item);
        $this->assertArrayNotHasKey('opened_by', $item);
    }

    public function test_an_inactivated_customer_still_shows_its_old_tickets(): void
    {
        $customer = Customer::factory()->create();
        Ticket::factory()->for($customer)->closed()->create(['title' => 'Chamado antigo']);
        Ticket::factory()->for($customer)->inProgress()->create();

        $this->actingAs($this->admin)
            ->deleteJson("/api/admin/customers/{$customer->id}")
            ->assertStatus(200);

        $response = $this->actingAs($this->admin)->getJson("/api/admin/customers/{$customer->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'inactive');
        $response->assertJsonCount(2, 'data.tickets');
        $response->assertJsonPath('data.tickets_count', 2);
        $response->assertJsonPath('data.open_tickets_count', 1);
        $this->assertContains('Chamado antigo', array_column($response->json('data.tickets'), 'title'));
    }

    public function test_a_customer_without_tickets_returns_an_empty_list_and_zero_counters(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($this->admin)->getJson("/api/admin/customers/{$customer->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.tickets', []);
        $response->assertJsonPath('data.tickets_count', 0);
        $response->assertJsonPath('data.open_tickets_count', 0);
    }

    public function test_the_customer_listing_does_not_carry_tickets(): void
    {
        $customer = Customer::factory()->create();
        Ticket::factory()->for($customer)->count(2)->create();

        $response = $this->actingAs($this->admin)->getJson('/api/admin/customers');

        $response->assertStatus(200);
        $item = $response->json('data.0');
        $this->assertSame($customer->id, $item['id']);
        $this->assertArrayNotHasKey('tickets', $item);
        $this->assertArrayNotHasKey('tickets_count', $item);
        $this->assertArrayNotHasKey('open_tickets_count', $item);
    }

    public function test_the_detail_does_not_run_one_query_per_ticket(): void
    {
        $agent = User::factory()->admin()->create();

        $single = Customer::factory()->create();
        $ticket = Ticket::factory()->for($single)->assignedTo($agent)->create();
        TicketComment::factory()->for($ticket)->create();

        $many = Customer::factory()->create();
        Ticket::factory()->for($many)->assignedTo($agent)->count(5)->create()
            ->each(fn (Ticket $ticket) => TicketComment::factory()->for($ticket)->create());

        $this->assertSame(
            $this->countQueriesFor("/api/admin/customers/{$single->id}", 1),
            $this->countQueriesFor("/api/admin/customers/{$many->id}", 5),
        );
    }

    private function countQueriesFor(string $uri, int $expectedTickets): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->admin)
            ->getJson($uri)
            ->assertStatus(200)
            ->assertJsonCount($expectedTickets, 'data.tickets');

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
