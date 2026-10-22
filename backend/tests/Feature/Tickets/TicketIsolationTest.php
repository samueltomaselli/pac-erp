<?php

namespace Tests\Feature\Tickets;

use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regra mais importante da sprint: um cliente nunca enxerga nada de outro
 * cliente, nem trocando o id na URL ou no corpo. Chamado alheio responde 404.
 *
 * Helpers compartilhados com os casos de comentário, anexo e download:
 * - customerWithTicket(): cliente com um chamado aberto por ele mesmo
 * - twoCustomers(): A e B, cada um com o próprio chamado
 */
class TicketIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_lists_only_its_own_tickets(): void
    {
        [$a, $ticketA, $b, $ticketB] = $this->twoCustomers();
        Ticket::factory()->count(2)->for($b)->create();

        $response = $this->actingAs($a->user)->getJson('/api/customer/tickets');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ticketA->id)
            ->assertJsonPath('data.0.customer.id', $a->id);

        $this->assertNotContains($ticketB->id, collect($response->json('data'))->pluck('id'));
    }

    public function test_a_customer_gets_404_for_another_customers_ticket(): void
    {
        [$a, , , $ticketB] = $this->twoCustomers();

        $this->actingAs($a->user)->getJson("/api/customer/tickets/{$ticketB->id}")
            ->assertStatus(404)
            ->assertJsonMissingPath('data')
            ->assertDontSee($ticketB->title);
    }

    public function test_another_customers_ticket_looks_the_same_as_a_missing_one(): void
    {
        [$a, , , $ticketB] = $this->twoCustomers();

        $foreign = $this->actingAs($a->user)->getJson("/api/customer/tickets/{$ticketB->id}");
        $missing = $this->getJson('/api/customer/tickets/999999');

        $foreign->assertStatus(404);
        $missing->assertStatus(404);
        $this->assertSame($missing->json(), $foreign->json());
    }

    public function test_a_customer_sees_its_own_ticket_detail(): void
    {
        [$customer, $ticket] = $this->customerWithTicket();

        $this->actingAs($customer->user)->getJson("/api/customer/tickets/{$ticket->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $ticket->id)
            ->assertJsonPath('data.customer.id', $customer->id)
            ->assertJsonMissingPath('data.allowed_transitions');
    }

    public function test_a_customer_cannot_open_a_ticket_in_another_customers_name(): void
    {
        [$a, , $b] = $this->twoCustomers();

        $response = $this->actingAs($a->user)->postJson('/api/customer/tickets', [
            'title' => 'Tentativa de abrir no nome de B',
            'category' => 'problem',
            'description' => '...',
            'customer_id' => $b->id,
            'customer' => ['id' => $b->id],
            'user_id' => $b->user_id,
            'opened_by' => $b->user_id,
            'assigned_to' => $b->user_id,
        ]);

        $response->assertStatus(201)->assertJsonPath('data.customer.id', $a->id);

        $ticket = Ticket::findOrFail($response->json('data.id'));
        $this->assertSame($a->id, $ticket->customer_id);
        $this->assertSame($a->user_id, $ticket->opened_by);
        $this->assertNull($ticket->assigned_to);
        $this->assertSame(1, $b->tickets()->count());
    }

    public function test_the_listing_total_and_pagination_do_not_reveal_other_customers_tickets(): void
    {
        [$a, , $b] = $this->twoCustomers();
        Ticket::factory()->for($a)->openedByCustomer()->create();
        Ticket::factory()->count(30)->for($b)->create();

        $this->actingAs($a->user)->getJson('/api/customer/tickets?per_page=15')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 1);

        $this->getJson('/api/customer/tickets?per_page=1&page=3')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_the_status_filter_stays_inside_the_customers_own_tickets(): void
    {
        [$a, , $b] = $this->twoCustomers();
        Ticket::factory()->for($b)->resolved()->create();

        $this->actingAs($a->user)->getJson('/api/customer/tickets?status=resolved')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_a_customer_id_in_the_query_string_is_ignored(): void
    {
        [$a, $ticketA, $b] = $this->twoCustomers();

        $this->actingAs($a->user)->getJson("/api/customer/tickets?customer_id={$b->id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ticketA->id);
    }

    public function test_switching_users_does_not_leak_tickets(): void
    {
        [$a, $ticketA, $b, $ticketB] = $this->twoCustomers();

        $this->actingAs($a->user)->getJson("/api/customer/tickets/{$ticketA->id}")->assertStatus(200);

        $this->freshBrowserSession();

        $this->actingAs($b->user)->getJson("/api/customer/tickets/{$ticketA->id}")->assertStatus(404);
        $this->getJson("/api/customer/tickets/{$ticketB->id}")->assertStatus(200);
        $this->getJson('/api/customer/tickets')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ticketB->id);
    }

    public function test_a_customer_user_without_a_linked_record_gets_a_clean_404(): void
    {
        $orphan = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($orphan);

        $this->getJson('/api/customer/tickets')
            ->assertStatus(404)
            ->assertExactJson(['message' => 'Nenhum cliente vinculado a este acesso.']);
        $this->getJson("/api/customer/tickets/{$ticket->id}")
            ->assertStatus(404)
            ->assertExactJson(['message' => 'Nenhum cliente vinculado a este acesso.']);
        $this->postJson('/api/customer/tickets', [
            'title' => 'Sem cadastro',
            'category' => 'other',
            'description' => '...',
            'customer_id' => $ticket->customer_id,
        ])->assertStatus(404)
            ->assertExactJson(['message' => 'Nenhum cliente vinculado a este acesso.']);

        $this->assertDatabaseCount('tickets', 1);
    }

    /**
     * Cliente ativo com um chamado aberto por ele mesmo.
     *
     * @return array{0: Customer, 1: Ticket}
     */
    private function customerWithTicket(): array
    {
        $customer = Customer::factory()->create();
        $ticket = Ticket::factory()->for($customer)->openedByCustomer()->create();

        return [$customer, $ticket];
    }

    /**
     * Clientes A e B, cada um com o próprio chamado.
     *
     * @return array{0: Customer, 1: Ticket, 2: Customer, 3: Ticket}
     */
    private function twoCustomers(): array
    {
        [$a, $ticketA] = $this->customerWithTicket();
        [$b, $ticketB] = $this->customerWithTicket();

        return [$a, $ticketA, $b, $ticketB];
    }
}
