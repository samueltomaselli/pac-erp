<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TicketDatesTest extends TestCase
{
    use RefreshDatabase;

    private const OPENED = '2026-11-03 09:00:00';

    private const EVENT = '2026-11-03 15:30:00';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo(Carbon::parse(self::OPENED));
    }

    public function test_opened_at_is_the_opening_time_and_never_changes(): void
    {
        $admin = User::factory()->admin()->create();
        [$customer, $ticket] = $this->customerTicket(TicketStatus::Open);

        $this->actingAs($admin)->getJson("/api/admin/tickets/{$ticket->id}")
            ->assertJsonPath('data.opened_at', $this->iso(self::OPENED));

        $this->travelTo(Carbon::parse(self::EVENT));
        $this->putJson("/api/admin/tickets/{$ticket->id}", ['title' => 'Novo título'])->assertStatus(200);
        $this->postJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'in_progress'])->assertStatus(200);
        $this->putJson("/api/admin/tickets/{$ticket->id}/assignee", ['assigned_to' => $admin->id])->assertStatus(200);
        $this->postJson("/api/admin/tickets/{$ticket->id}/comments", ['body' => 'Verificando.'])->assertStatus(201);
        $this->post("/api/admin/tickets/{$ticket->id}/attachments", [
            'attachments' => [UploadedFile::fake()->create('print.png', 10, 'image/png')],
        ], ['Accept' => 'application/json'])->assertStatus(201);
        $this->postJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'resolved'])->assertStatus(200);

        $this->travelTo(Carbon::parse(self::EVENT)->addDay());
        $this->postJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'closed'])->assertStatus(200);

        $this->getJson("/api/admin/tickets/{$ticket->id}")
            ->assertJsonPath('data.opened_at', $this->iso(self::OPENED));

        $this->freshBrowserSession()->actingAs($customer->user)
            ->getJson("/api/customer/tickets/{$ticket->id}")
            ->assertJsonPath('data.opened_at', $this->iso(self::OPENED));
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function editableFields(): array
    {
        return [
            'título' => ['title', 'Título corrigido'],
            'categoria' => ['category', 'billing'],
            'descrição' => ['description', 'Descrição corrigida'],
            'prioridade' => ['priority', 'high'],
        ];
    }

    #[DataProvider('editableFields')]
    public function test_editing_a_field_updates_the_last_update(string $field, mixed $value): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->inProgress()->create([
            'category' => 'question',
            'priority' => TicketPriority::Medium,
        ]);

        $this->travelTo(Carbon::parse(self::EVENT));
        $this->actingAs($admin)->putJson("/api/admin/tickets/{$ticket->id}", [$field => $value])
            ->assertStatus(200)
            ->assertJsonPath('data.updated_at', $this->iso(self::EVENT));

        $this->assertUpdatedAt(self::EVENT, $ticket);
    }

    public function test_changing_the_status_updates_the_last_update(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->open()->create();

        $this->travelTo(Carbon::parse(self::EVENT));
        $this->actingAs($admin)->postJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'in_progress'])
            ->assertStatus(200)
            ->assertJsonPath('data.updated_at', $this->iso(self::EVENT));

        $this->assertUpdatedAt(self::EVENT, $ticket);
    }

    public function test_the_automatic_exit_from_waiting_customer_updates_the_last_update(): void
    {
        [$customer, $ticket] = $this->customerTicket(TicketStatus::WaitingCustomer);

        $this->travelTo(Carbon::parse(self::EVENT));
        $this->actingAs($customer->user)->postJson("/api/customer/tickets/{$ticket->id}/comments", [
            'body' => 'Segue o retorno.',
        ])->assertStatus(201);

        $ticket->refresh();
        $this->assertSame(TicketStatus::InProgress, $ticket->status);
        $this->assertUpdatedAt(self::EVENT, $ticket);
    }

    public function test_assigning_and_removing_the_assignee_update_the_last_update(): void
    {
        $admin = User::factory()->admin()->create();
        $ana = User::factory()->admin()->create();
        $ticket = Ticket::factory()->inProgress()->create();

        $this->travelTo(Carbon::parse(self::EVENT));
        $this->actingAs($admin)->putJson("/api/admin/tickets/{$ticket->id}/assignee", ['assigned_to' => $ana->id])
            ->assertStatus(200)
            ->assertJsonPath('data.updated_at', $this->iso(self::EVENT));
        $this->assertUpdatedAt(self::EVENT, $ticket);

        $removal = Carbon::parse(self::EVENT)->addHour();
        $this->travelTo($removal);
        $this->putJson("/api/admin/tickets/{$ticket->id}/assignee", ['assigned_to' => null])
            ->assertStatus(200)
            ->assertJsonPath('data.assignee', null)
            ->assertJsonPath('data.updated_at', $removal->toIso8601String());
        $this->assertUpdatedAt($removal, $ticket);
    }

    public function test_an_admin_comment_updates_the_last_update(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->inProgress()->create();

        $this->travelTo(Carbon::parse(self::EVENT));
        $this->actingAs($admin)->postJson("/api/admin/tickets/{$ticket->id}/comments", ['body' => 'Verificando.'])
            ->assertStatus(201);

        $this->assertUpdatedAt(self::EVENT, $ticket);
    }

    public function test_a_customer_comment_updates_the_last_update(): void
    {
        [$customer, $ticket] = $this->customerTicket(TicketStatus::InProgress);

        $this->travelTo(Carbon::parse(self::EVENT));
        $this->actingAs($customer->user)->postJson("/api/customer/tickets/{$ticket->id}/comments", [
            'body' => 'Alguma novidade?',
        ])->assertStatus(201);

        $this->assertUpdatedAt(self::EVENT, $ticket);
    }

    public function test_an_admin_attachment_without_comment_updates_the_last_update(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->inProgress()->create();

        $this->travelTo(Carbon::parse(self::EVENT));
        $this->actingAs($admin)->post("/api/admin/tickets/{$ticket->id}/attachments", [
            'attachments' => [UploadedFile::fake()->create('contrato.pdf', 200, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $this->assertSame(0, $ticket->comments()->count());
        $this->assertUpdatedAt(self::EVENT, $ticket);
    }

    public function test_a_customer_attachment_without_comment_updates_the_last_update(): void
    {
        [$customer, $ticket] = $this->customerTicket(TicketStatus::InProgress);

        $this->travelTo(Carbon::parse(self::EVENT));
        $this->actingAs($customer->user)->post("/api/customer/tickets/{$ticket->id}/attachments", [
            'attachments' => [UploadedFile::fake()->create('print.png', 10, 'image/png')],
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $this->assertSame(0, $ticket->comments()->count());
        $this->assertUpdatedAt(self::EVENT, $ticket);
    }

    public function test_opening_the_detail_does_not_change_the_last_update(): void
    {
        $admin = User::factory()->admin()->create();
        [$customer, $ticket] = $this->customerTicket(TicketStatus::InProgress);

        $this->travelTo(Carbon::parse(self::EVENT));
        $this->actingAs($admin)->getJson("/api/admin/tickets/{$ticket->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.updated_at', $this->iso(self::OPENED));

        $this->freshBrowserSession()->actingAs($customer->user)
            ->getJson("/api/customer/tickets/{$ticket->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.updated_at', $this->iso(self::OPENED));
        $this->getJson('/api/customer/tickets')->assertStatus(200);

        $this->assertUpdatedAt(self::OPENED, $ticket);
    }

    public function test_resolved_at_is_set_on_resolve_and_cleared_on_reopen(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->inProgress()->create();

        $this->travelTo(Carbon::parse(self::EVENT));
        $this->actingAs($admin)->postJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'resolved'])
            ->assertStatus(200)
            ->assertJsonPath('data.resolved_at', $this->iso(self::EVENT))
            ->assertJsonPath('data.closed_at', null);

        $this->travelTo(Carbon::parse(self::EVENT)->addHour());
        $this->postJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'in_progress'])
            ->assertStatus(200)
            ->assertJsonPath('data.resolved_at', null);

        $this->assertNull($ticket->fresh()->resolved_at);
    }

    public function test_closed_at_is_set_on_close_and_does_not_change_afterwards(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->inProgress()->create();

        $this->travelTo(Carbon::parse(self::EVENT));
        $this->actingAs($admin)->postJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'resolved'])
            ->assertStatus(200);

        $closing = Carbon::parse(self::EVENT)->addDay();
        $this->travelTo($closing);
        $this->postJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'closed'])
            ->assertStatus(200)
            ->assertJsonPath('data.resolved_at', $this->iso(self::EVENT))
            ->assertJsonPath('data.closed_at', $closing->toIso8601String());

        $this->travelTo($closing->copy()->addDay());
        $this->postJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'in_progress'])
            ->assertStatus(422);

        $this->assertSame($closing->toDateTimeString(), $ticket->fresh()->closed_at->toDateTimeString());
    }

    public function test_the_customer_json_has_the_four_dates_with_the_admin_values(): void
    {
        $admin = User::factory()->admin()->create();
        [$customer, $ticket] = $this->customerTicket(TicketStatus::InProgress);

        $this->travelTo(Carbon::parse(self::EVENT));
        $this->actingAs($admin)->postJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'resolved']);
        $this->travelTo(Carbon::parse(self::EVENT)->addDay());
        $this->postJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'closed']);

        $adminDates = $this->dates($this->getJson("/api/admin/tickets/{$ticket->id}")->json('data'));

        $customerDetail = $this->freshBrowserSession()->actingAs($customer->user)
            ->getJson("/api/customer/tickets/{$ticket->id}")
            ->assertStatus(200)
            ->json('data');
        $customerListItem = $this->getJson('/api/customer/tickets')->json('data.0');

        $this->assertSame([
            'opened_at' => $this->iso(self::OPENED),
            'updated_at' => Carbon::parse(self::EVENT)->addDay()->toIso8601String(),
            'resolved_at' => $this->iso(self::EVENT),
            'closed_at' => Carbon::parse(self::EVENT)->addDay()->toIso8601String(),
        ], $adminDates);
        $this->assertSame($adminDates, $this->dates($customerDetail));
        $this->assertSame($adminDates, $this->dates($customerListItem));
    }

    public function test_the_customer_list_puts_the_ticket_with_news_on_top(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        $older = Ticket::factory()->for($customer)->openedByCustomer()->inProgress()->create(['title' => 'Mais antigo']);

        $this->travelTo(Carbon::parse(self::OPENED)->addDay());
        $newer = Ticket::factory()->for($customer)->openedByCustomer()->inProgress()->create(['title' => 'Mais novo']);

        $this->actingAs($customer->user)->getJson('/api/customer/tickets')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);

        $this->travelTo(Carbon::parse(self::OPENED)->addDays(2));
        $this->freshBrowserSession()->actingAs($admin)
            ->postJson("/api/admin/tickets/{$older->id}/comments", ['body' => 'Temos novidade.'])
            ->assertStatus(201);

        $this->freshBrowserSession()->actingAs($customer->user)->getJson('/api/customer/tickets')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $older->id)
            ->assertJsonPath('data.1.id', $newer->id);
    }

    public function test_tickets_updated_at_the_same_time_fall_back_to_the_highest_id(): void
    {
        $customer = Customer::factory()->create();
        $first = Ticket::factory()->for($customer)->openedByCustomer()->create();
        $second = Ticket::factory()->for($customer)->openedByCustomer()->create();

        $this->actingAs($customer->user)->getJson('/api/customer/tickets')
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.1.id', $first->id);
    }

    public function test_the_new_order_does_not_bring_tickets_from_another_customer(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        $mine = Ticket::factory()->for($customer)->openedByCustomer()->inProgress()->create();
        $other = Ticket::factory()->inProgress()->create();

        $this->travelTo(Carbon::parse(self::EVENT));
        $this->actingAs($admin)->postJson("/api/admin/tickets/{$other->id}/comments", ['body' => 'Atualização de outro cliente.'])
            ->assertStatus(201);

        $response = $this->freshBrowserSession()->actingAs($customer->user)->getJson('/api/customer/tickets')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('meta.total', 1);

        $this->assertNotContains($other->id, array_column($response->json('data'), 'id'));
    }

    /**
     * @return array{Customer, Ticket}
     */
    private function customerTicket(TicketStatus $status): array
    {
        $customer = Customer::factory()->create();
        $ticket = Ticket::factory()->for($customer)->openedByCustomer()->status($status)->create();

        return [$customer, $ticket];
    }

    private function assertUpdatedAt(Carbon|string $expected, Ticket $ticket): void
    {
        $this->assertSame(
            Carbon::parse($expected)->toDateTimeString(),
            $ticket->fresh()->updated_at->toDateTimeString(),
        );
    }

    private function iso(string $dateTime): string
    {
        return Carbon::parse($dateTime)->toIso8601String();
    }

    /**
     * @param  array<string, mixed>  $ticket
     * @return array<string, string|null>
     */
    private function dates(array $ticket): array
    {
        return [
            'opened_at' => $ticket['opened_at'],
            'updated_at' => $ticket['updated_at'],
            'resolved_at' => $ticket['resolved_at'],
            'closed_at' => $ticket['closed_at'],
        ];
    }
}
