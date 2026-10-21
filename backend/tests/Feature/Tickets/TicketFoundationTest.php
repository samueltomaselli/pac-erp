<?php

namespace Tests\Feature\Tickets;

use App\Http\Resources\TicketResource;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TicketFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_is_formatted_as_ch_id(): void
    {
        $ticket = Ticket::factory()->create();

        $this->assertSame('#CH-'.$ticket->id, $ticket->reference);
    }

    public function test_a_new_ticket_starts_open_with_medium_priority(): void
    {
        $customer = Customer::factory()->create();

        $ticket = Ticket::create([
            'customer_id' => $customer->id,
            'title' => 'Dúvida sobre a fatura',
            'category' => 'billing',
            'description' => 'Valor diferente do combinado.',
        ]);

        $this->assertSame('open', $ticket->fresh()->status->value);
        $this->assertSame('medium', $ticket->fresh()->priority->value);
    }

    public function test_a_new_comment_touches_the_ticket_updated_at(): void
    {
        $this->travelTo(now()->subDays(2)->setTime(9, 0));
        $ticket = Ticket::factory()->create();

        $this->travelBack();
        $this->travelTo(now()->setTime(14, 2));
        TicketComment::factory()->for($ticket)->create();

        $this->assertTrue($ticket->fresh()->updated_at->equalTo(now()));
        $this->assertTrue($ticket->fresh()->created_at->lessThan(now()->subDay()));
    }

    public function test_comments_are_ordered_by_creation(): void
    {
        $ticket = Ticket::factory()->create();

        $this->travelTo(now()->addHour());
        $later = TicketComment::factory()->for($ticket)->create();
        $this->travelTo(now()->subMinutes(30));
        $earlier = TicketComment::factory()->for($ticket)->create();

        $this->assertSame([$earlier->id, $later->id], $ticket->comments()->pluck('id')->all());
    }

    public function test_allowed_transitions_are_only_shown_to_admins(): void
    {
        $customer = Customer::factory()->create();
        $ticket = Ticket::factory()->for($customer)->inProgress()->create();

        $asAdmin = $this->resolveAs(User::factory()->admin()->create(), $ticket);
        $asCustomer = $this->resolveAs($customer->user, $ticket);

        $this->assertArrayHasKey('allowed_transitions', $asAdmin);
        $this->assertArrayNotHasKey('allowed_transitions', $asCustomer);
        $this->assertSame('in_progress', $asCustomer['status']);
    }

    public function test_the_detail_payload_matches_the_contract(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Ana']);
        $customer = Customer::factory()->create(['name' => 'Imobiliária Alvorada']);
        $ticket = Ticket::factory()->for($customer)->openedByCustomer()->assignedTo($admin)->create();
        $comment = TicketComment::factory()->for($ticket)->byCustomer()->create();
        $attachment = TicketAttachment::factory()->forComment($comment)->create();

        $ticket->load(['customer', 'assignee', 'openedBy', 'comments.author', 'comments.attachments', 'attachments'])
            ->loadCount('comments');
        $payload = $this->resolveAs($admin, $ticket);

        $this->assertSame(['id' => $customer->id, 'name' => 'Imobiliária Alvorada'], $payload['customer']);
        $this->assertSame(['id' => $admin->id, 'name' => 'Ana'], $payload['assignee']);
        $this->assertSame('customer', $payload['opened_by']['role']);
        $this->assertSame(1, $payload['comments_count']);
        $this->assertSame('customer', $payload['comments'][0]['author']['role']);
        $this->assertSame($attachment->id, $payload['comments'][0]['attachments'][0]['id']);
        $this->assertSame($comment->id, $payload['attachments'][0]['comment_id']);
        $this->assertArrayNotHasKey('path', $payload['attachments'][0]);
        $this->assertArrayNotHasKey('disk', $payload['attachments'][0]);
    }

    public function test_the_listing_payload_leaves_out_comments_and_attachments(): void
    {
        $ticket = Ticket::factory()->create();
        TicketComment::factory()->count(2)->for($ticket)->create();

        $payload = $this->resolveAs(User::factory()->admin()->create(), $ticket->loadCount('comments'));

        $this->assertSame(2, $payload['comments_count']);
        $this->assertArrayNotHasKey('comments', $payload);
        $this->assertArrayNotHasKey('attachments', $payload);
    }

    public function test_the_policy_scopes_customers_to_their_own_tickets(): void
    {
        $owner = Customer::factory()->create();
        $other = Customer::factory()->create();
        $ticket = Ticket::factory()->for($owner)->create();

        $this->assertTrue(Gate::forUser(User::factory()->admin()->create())->allows('view', $ticket));
        $this->assertTrue(Gate::forUser($owner->user)->allows('view', $ticket));

        $denied = Gate::forUser($other->user)->inspect('view', $ticket);
        $this->assertTrue($denied->denied());
        $this->assertSame(404, $denied->status());
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveAs(User $user, Ticket $ticket): array
    {
        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        return (new TicketResource($ticket))->resolve($request);
    }
}
