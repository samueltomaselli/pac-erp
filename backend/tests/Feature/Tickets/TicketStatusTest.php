<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TicketStatusTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('transitionMatrix')]
    public function test_the_transition_matrix_is_enforced(TicketStatus $from, TicketStatus $to, bool $allowed): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->status($from)->create();

        $response = $this->actingAs($admin)->postJson("/api/admin/tickets/{$ticket->id}/status", [
            'status' => $to->value,
        ]);

        if ($allowed) {
            $response->assertOk()
                ->assertJsonPath('data.status', $to->value)
                ->assertJsonPath('data.status_label', $to->label());
            $this->assertSame($to, $ticket->fresh()->status);

            return;
        }

        $response->assertStatus(422)
            ->assertJsonPath('message', "Não é possível mudar de {$from->label()} para {$to->label()}.");
        $this->assertSame($from, $ticket->fresh()->status);
    }

    /**
     * Os 25 pares de/para (5×5), válidos e inválidos.
     */
    public static function transitionMatrix(): array
    {
        $allowed = [
            'open' => ['in_progress'],
            'in_progress' => ['waiting_customer', 'resolved'],
            'waiting_customer' => ['in_progress', 'resolved'],
            'resolved' => ['closed', 'in_progress'],
            'closed' => [],
        ];

        $cases = [];
        foreach (TicketStatus::cases() as $from) {
            foreach (TicketStatus::cases() as $to) {
                $cases["{$from->value} -> {$to->value}"] = [$from, $to, in_array($to->value, $allowed[$from->value], true)];
            }
        }

        return $cases;
    }

    public function test_an_open_ticket_cannot_skip_straight_to_resolved(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->open()->create();

        $this->actingAs($admin)->postJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'resolved'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Não é possível mudar de Aberto para Resolvido.');

        $this->assertSame(TicketStatus::Open, $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->resolved_at);
    }

    public function test_a_closed_ticket_cannot_leave_closed(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->closed()->create();

        foreach (TicketStatus::cases() as $to) {
            $this->actingAs($admin)->postJson("/api/admin/tickets/{$ticket->id}/status", ['status' => $to->value])
                ->assertStatus(422);
        }

        $this->assertSame(TicketStatus::Closed, $ticket->fresh()->status);
    }

    public function test_resolving_records_resolved_at_and_reopening_clears_it(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->inProgress()->create();

        $this->travelTo(now()->setTime(10, 30));
        $this->actingAs($admin)->postJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'resolved'])
            ->assertOk()
            ->assertJsonPath('data.resolved_at', now()->toIso8601String());
        $this->assertTrue($ticket->fresh()->resolved_at->equalTo(now()));

        $this->travel(2)->hours();
        $this->actingAs($admin)->postJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('data.resolved_at', null);
        $this->assertNull($ticket->fresh()->resolved_at);
    }

    public function test_closing_records_closed_at(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->resolved()->create();

        $this->travelTo(now()->addDay()->setTime(16, 0));
        $this->actingAs($admin)->postJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'closed'])
            ->assertOk()
            ->assertJsonPath('data.closed_at', now()->toIso8601String());

        $this->assertTrue($ticket->fresh()->closed_at->equalTo(now()));
        $this->assertNotNull($ticket->fresh()->resolved_at);
    }

    public function test_an_unknown_status_is_a_validation_error(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->open()->create();

        $this->actingAs($admin)->postJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'archived'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->actingAs($admin)->postJson("/api/admin/tickets/{$ticket->id}/status", [])
            ->assertStatus(422)
            ->assertJsonPath('errors.status.0', 'O campo status é obrigatório.');
    }

    public function test_status_cannot_be_mass_assigned(): void
    {
        $customer = Customer::factory()->create();

        $ticket = Ticket::create([
            'customer_id' => $customer->id,
            'title' => 'Chamado',
            'category' => 'question',
            'description' => 'Descrição',
            'status' => 'closed',
            'closed_at' => now(),
        ]);
        $this->assertSame(TicketStatus::Open, $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->closed_at);

        $ticket->update(['title' => 'Título novo', 'status' => 'resolved', 'resolved_at' => now()]);
        $this->assertSame('Título novo', $ticket->fresh()->title);
        $this->assertSame(TicketStatus::Open, $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->resolved_at);
    }

    public function test_a_customer_cannot_use_the_admin_status_endpoint(): void
    {
        $customer = Customer::factory()->create();
        $ticket = Ticket::factory()->for($customer)->open()->create();

        $this->actingAs($customer->user)->postJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'in_progress'])
            ->assertStatus(403);

        $this->assertSame(TicketStatus::Open, $ticket->fresh()->status);
    }

    public function test_the_admin_payload_lists_allowed_transitions(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->inProgress()->create();

        $this->actingAs($admin)->postJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'waiting_customer'])
            ->assertOk()
            ->assertJsonPath('data.allowed_transitions', [
                ['status' => 'in_progress', 'label' => 'Em atendimento'],
                ['status' => 'resolved', 'label' => 'Resolvido'],
            ]);
    }
}
