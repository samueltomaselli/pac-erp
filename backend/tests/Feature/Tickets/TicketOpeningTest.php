<?php

namespace Tests\Feature\Tickets;

use App\Actions\Customers\DeactivateCustomer;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketOpeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_opens_a_ticket_for_an_active_customer_with_the_chosen_priority(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/admin/tickets', [
            'customer_id' => $customer->id,
            'title' => 'Erro ao emitir boleto',
            'category' => 'billing',
            'description' => 'O boleto sai com o valor errado.',
            'priority' => 'urgent',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.customer.id', $customer->id)
            ->assertJsonPath('data.priority', 'urgent')
            ->assertJsonPath('data.priority_label', 'Urgente')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.opened_by.id', $admin->id)
            ->assertJsonPath('data.opened_by.role', 'admin')
            ->assertJsonPath('data.reference', '#CH-'.$response->json('data.id'));

        $this->assertDatabaseHas('tickets', [
            'id' => $response->json('data.id'),
            'customer_id' => $customer->id,
            'opened_by' => $admin->id,
            'priority' => 'urgent',
            'status' => 'open',
        ]);
    }

    public function test_an_admin_ticket_without_priority_starts_medium(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($admin)->postJson('/api/admin/tickets', [
            'customer_id' => $customer->id,
            'title' => 'Dúvida sobre o contrato',
            'category' => 'question',
            'description' => 'Qual a vigência?',
        ])->assertStatus(201)->assertJsonPath('data.priority', 'medium');
    }

    public function test_a_customer_opens_a_ticket_linked_to_its_own_record(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($customer->user)->postJson('/api/customer/tickets', [
            'title' => 'Não consigo acessar o painel',
            'category' => 'problem',
            'description' => 'Aparece erro 500 depois do login.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.customer.id', $customer->id)
            ->assertJsonPath('data.opened_by.id', $customer->user_id)
            ->assertJsonPath('data.opened_by.role', 'customer')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.priority', 'medium')
            ->assertJsonMissingPath('data.allowed_transitions');

        $this->assertDatabaseHas('tickets', [
            'id' => $response->json('data.id'),
            'customer_id' => $customer->id,
            'opened_by' => $customer->user_id,
        ]);
    }

    public function test_a_customer_cannot_choose_the_priority(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($customer->user)->postJson('/api/customer/tickets', [
            'title' => 'Sistema fora do ar',
            'category' => 'problem',
            'description' => 'Nada carrega.',
            'priority' => 'urgent',
        ]);

        $response->assertStatus(201)->assertJsonPath('data.priority', 'medium');
        $this->assertSame('medium', Ticket::find($response->json('data.id'))->priority->value);
    }

    public function test_every_ticket_starts_open_even_if_the_body_sends_a_status(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($admin)->postJson('/api/admin/tickets', [
            'customer_id' => $customer->id,
            'title' => 'Pelo admin',
            'category' => 'request',
            'description' => '...',
            'status' => 'closed',
        ])->assertStatus(201)->assertJsonPath('data.status', 'open');

        $this->freshBrowserSession();

        $this->actingAs($customer->user)->postJson('/api/customer/tickets', [
            'title' => 'Pelo cliente',
            'category' => 'request',
            'description' => '...',
            'status' => 'resolved',
        ])->assertStatus(201)->assertJsonPath('data.status', 'open');

        $this->assertSame(['open'], Ticket::pluck('status')->map->value->unique()->values()->all());
    }

    public function test_an_admin_cannot_open_a_ticket_for_an_inactive_customer(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        app(DeactivateCustomer::class)->handle($customer);

        $this->actingAs($admin)->postJson('/api/admin/tickets', [
            'customer_id' => $customer->id,
            'title' => 'Chamado novo',
            'category' => 'other',
            'description' => '...',
        ])->assertStatus(422)
            ->assertJson(['message' => 'Não é possível abrir chamado para um cliente inativo.']);

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_an_inactive_customer_cannot_open_a_ticket_but_still_sees_the_old_ones(): void
    {
        $customer = Customer::factory()->create();
        $old = Ticket::factory()->for($customer)->openedByCustomer()->create();
        app(DeactivateCustomer::class)->handle($customer);

        $this->actingAs($customer->user)->postJson('/api/customer/tickets', [
            'title' => 'Chamado novo',
            'category' => 'other',
            'description' => '...',
        ])->assertStatus(422)
            ->assertJson(['message' => 'Não é possível abrir chamado para um cliente inativo.']);

        $this->getJson('/api/customer/tickets')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $old->id);

        $this->getJson("/api/customer/tickets/{$old->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $old->id);

        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_an_inactive_customers_tickets_stay_visible_to_the_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create(['name' => 'Imobiliária Alvorada']);
        $ticket = Ticket::factory()->for($customer)->create();
        app(DeactivateCustomer::class)->handle($customer);

        $this->actingAs($admin)->getJson("/api/admin/tickets/{$ticket->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.customer.name', 'Imobiliária Alvorada');

        $this->getJson('/api/admin/tickets?customer_id='.$customer->id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_title_category_and_description_are_required_in_portuguese(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)->postJson('/api/customer/tickets', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.title.0', 'O campo título é obrigatório.')
            ->assertJsonPath('errors.category.0', 'O campo categoria é obrigatório.')
            ->assertJsonPath('errors.description.0', 'O campo descrição é obrigatório.');
    }

    public function test_the_admin_form_requires_the_customer_in_portuguese(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/admin/tickets', [
            'title' => 'Sem cliente',
            'category' => 'other',
            'description' => '...',
        ])->assertStatus(422)
            ->assertJsonPath('errors.customer_id.0', 'O campo cliente é obrigatório.');
    }

    public function test_a_category_outside_the_enum_is_rejected_in_portuguese(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)->postJson('/api/customer/tickets', [
            'title' => 'Categoria inventada',
            'category' => 'elogio',
            'description' => '...',
        ])->assertStatus(422)
            ->assertJsonPath('errors.category.0', 'O categoria selecionado é inválido.');

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_an_admin_priority_outside_the_enum_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($admin)->postJson('/api/admin/tickets', [
            'customer_id' => $customer->id,
            'title' => 'Prioridade inventada',
            'category' => 'problem',
            'description' => '...',
            'priority' => 'critical',
        ])->assertStatus(422)->assertJsonValidationErrors('priority');
    }
}
