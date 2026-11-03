<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketInteractionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_an_admin_comments_and_the_author_and_time_come_back(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Ana']);
        $ticket = Ticket::factory()->inProgress()->create();
        $this->travelTo(now()->setTime(14, 2));

        $response = $this->actingAs($admin)->postJson("/api/admin/tickets/{$ticket->id}/comments", [
            'body' => 'Estamos verificando o boleto.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.body', 'Estamos verificando o boleto.')
            ->assertJsonPath('data.author.name', 'Ana')
            ->assertJsonPath('data.author.role', 'admin')
            ->assertJsonPath('data.created_at', now()->toIso8601String());

        $this->assertDatabaseHas('ticket_comments', ['ticket_id' => $ticket->id, 'user_id' => $admin->id]);
    }

    public function test_a_customer_comments_on_its_own_ticket(): void
    {
        [$customer, $ticket] = $this->customerTicket(TicketStatus::InProgress);

        $this->actingAs($customer->user)->postJson("/api/customer/tickets/{$ticket->id}/comments", [
            'body' => 'Segue a informação pedida.',
        ])->assertStatus(201)
            ->assertJsonPath('data.author.role', 'customer');
    }

    public function test_comments_appear_in_chronological_order_on_the_detail(): void
    {
        $admin = User::factory()->admin()->create();
        [$customer, $ticket] = $this->customerTicket(TicketStatus::InProgress);

        $this->travelTo(now()->setTime(9, 0));
        $this->actingAs($admin)->postJson("/api/admin/tickets/{$ticket->id}/comments", ['body' => 'Primeiro']);
        $this->travelTo(now()->setTime(10, 0));
        $this->freshBrowserSession()->actingAs($customer->user)
            ->postJson("/api/customer/tickets/{$ticket->id}/comments", ['body' => 'Segundo']);

        $this->freshBrowserSession()->actingAs($admin)->getJson("/api/admin/tickets/{$ticket->id}")
            ->assertJsonPath('data.comments.0.body', 'Primeiro')
            ->assertJsonPath('data.comments.1.body', 'Segundo')
            ->assertJsonPath('data.comments.1.author.role', 'customer');
    }

    public function test_a_customer_comment_takes_the_ticket_out_of_waiting_customer(): void
    {
        [$customer, $ticket] = $this->customerTicket(TicketStatus::WaitingCustomer);

        $this->actingAs($customer->user)->postJson("/api/customer/tickets/{$ticket->id}/comments", [
            'body' => 'Respondendo.',
        ])->assertStatus(201);

        $this->assertSame(TicketStatus::InProgress, $ticket->fresh()->status);
    }

    public function test_an_admin_comment_does_not_change_waiting_customer(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->waitingCustomer()->create();

        $this->actingAs($admin)->postJson("/api/admin/tickets/{$ticket->id}/comments", [
            'body' => 'Lembrando que aguardamos o retorno.',
        ])->assertStatus(201);

        $this->assertSame(TicketStatus::WaitingCustomer, $ticket->fresh()->status);
    }

    public function test_a_customer_comment_does_not_reopen_a_resolved_ticket(): void
    {
        [$customer, $ticket] = $this->customerTicket(TicketStatus::Resolved);

        $this->actingAs($customer->user)->postJson("/api/customer/tickets/{$ticket->id}/comments", [
            'body' => 'Obrigado!',
        ])->assertStatus(201);

        $this->assertSame(TicketStatus::Resolved, $ticket->fresh()->status);
    }

    public function test_a_closed_ticket_refuses_comments_and_attachments(): void
    {
        [$customer, $ticket] = $this->customerTicket(TicketStatus::Closed);

        $this->actingAs($customer->user)->postJson("/api/customer/tickets/{$ticket->id}/comments", [
            'body' => 'Ainda tenho uma dúvida.',
        ])->assertStatus(422)
            ->assertJsonPath('errors.ticket.0', 'Chamado fechado não aceita novos comentários.');

        $this->postJson("/api/customer/tickets/{$ticket->id}/attachments", [
            'attachments' => [UploadedFile::fake()->create('print.png', 10, 'image/png')],
        ])->assertStatus(422)
            ->assertJsonPath('errors.ticket.0', 'Chamado fechado não aceita novos anexos.');

        $this->assertDatabaseCount('ticket_comments', 0);
        $this->assertDatabaseCount('ticket_attachments', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_a_comment_updates_the_ticket_last_update(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->inProgress()->create();
        $this->travelTo(now()->addDays(2));

        $this->actingAs($admin)->postJson("/api/admin/tickets/{$ticket->id}/comments", ['body' => 'Atualizando.']);

        $this->assertSame(now()->toDateTimeString(), $ticket->fresh()->updated_at->toDateTimeString());
    }

    public function test_the_comment_body_is_required_in_portuguese(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($admin)->postJson("/api/admin/tickets/{$ticket->id}/comments", ['body' => '']);

        $response->assertStatus(422)->assertJsonValidationErrors('body');
        $this->assertStringContainsString('obrigatório', $response->json('errors.body.0'));
    }

    public function test_comments_cannot_be_edited_or_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->create();
        $comment = $ticket->comments()->create(['user_id' => $admin->id, 'body' => 'Original']);

        $this->actingAs($admin)->putJson("/api/admin/tickets/{$ticket->id}/comments/{$comment->id}", ['body' => 'Alterado'])
            ->assertStatus(404);
        $this->deleteJson("/api/admin/tickets/{$ticket->id}/comments/{$comment->id}")
            ->assertStatus(404);

        $this->assertSame('Original', $comment->fresh()->body);
    }

    public function test_a_comment_with_attachments_stores_them_on_the_private_disk(): void
    {
        [$customer, $ticket] = $this->customerTicket(TicketStatus::InProgress);

        $response = $this->actingAs($customer->user)->post("/api/customer/tickets/{$ticket->id}/comments", [
            'body' => 'Segue o print.',
            'attachments' => [UploadedFile::fake()->image('erro.png')],
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201)
            ->assertJsonPath('data.attachments.0.original_name', 'erro.png')
            ->assertJsonPath('data.attachments.0.mime_type', 'image/png');

        $attachment = TicketAttachment::sole();
        $this->assertSame($response->json('data.id'), $attachment->comment_id);
        $this->assertSame($customer->user_id, $attachment->uploaded_by);
        $this->assertSame('local', $attachment->disk);
        $this->assertStringStartsWith("tickets/{$ticket->id}/", $attachment->path);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_an_attachment_can_go_straight_to_the_ticket(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($admin)->post("/api/admin/tickets/{$ticket->id}/attachments", [
            'attachments' => [
                UploadedFile::fake()->create('contrato.pdf', 200, 'application/pdf'),
                UploadedFile::fake()->create('planilha.csv', 5, 'text/csv'),
            ],
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201)->assertJsonCount(2, 'data');
        $this->assertSame(2, $ticket->attachments()->whereNull('comment_id')->count());
    }

    public function test_the_attachment_json_does_not_expose_path_or_disk(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($admin)->post("/api/admin/tickets/{$ticket->id}/attachments", [
            'attachments' => [UploadedFile::fake()->create('contrato.pdf', 20, 'application/pdf')],
        ], ['Accept' => 'application/json']);

        $response->assertJsonMissingPath('data.0.path')->assertJsonMissingPath('data.0.disk');

        $this->getJson("/api/admin/tickets/{$ticket->id}")
            ->assertJsonMissingPath('data.attachments.0.path')
            ->assertJsonMissingPath('data.attachments.0.disk');
    }

    public function test_more_than_five_files_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->create();
        $files = collect(range(1, 6))->map(fn ($i) => UploadedFile::fake()->create("arquivo{$i}.pdf", 10, 'application/pdf'))->all();

        $this->actingAs($admin)->post("/api/admin/tickets/{$ticket->id}/attachments", ['attachments' => $files], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('errors.attachments.0', 'Você pode enviar no máximo 5 arquivos.');

        $this->assertDatabaseCount('ticket_attachments', 0);
    }

    public function test_a_file_above_ten_megabytes_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($admin)->post("/api/admin/tickets/{$ticket->id}/attachments", [
            'attachments' => [UploadedFile::fake()->create('grande.pdf', 10_241, 'application/pdf')],
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $this->assertSame('Cada arquivo pode ter no máximo 10 MB.', $response->json('errors')['attachments.0'][0]);
    }

    public function test_a_file_type_outside_the_list_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($admin)->post("/api/admin/tickets/{$ticket->id}/comments", [
            'body' => 'Segue o executável.',
            'attachments' => [UploadedFile::fake()->create('programa.exe', 10, 'application/x-msdownload')],
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $this->assertSame('O tipo de arquivo enviado não é aceito.', $response->json('errors')['attachments.0'][0]);

        $this->assertDatabaseCount('ticket_comments', 0);
    }

    public function test_the_download_returns_the_file_with_its_original_name(): void
    {
        [$customer, $ticket] = $this->customerTicket(TicketStatus::InProgress);
        $this->actingAs($customer->user)->post("/api/customer/tickets/{$ticket->id}/attachments", [
            'attachments' => [UploadedFile::fake()->create('comprovante.pdf', 30, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertStatus(201);
        $attachment = TicketAttachment::sole();

        $this->get("/api/customer/ticket-attachments/{$attachment->id}")
            ->assertOk()
            ->assertDownload('comprovante.pdf');

        $this->freshBrowserSession()->actingAs(User::factory()->admin()->create())
            ->get("/api/admin/ticket-attachments/{$attachment->id}")
            ->assertOk()
            ->assertDownload('comprovante.pdf');
    }

    /**
     * @return array{0: Customer, 1: Ticket}
     */
    private function customerTicket(TicketStatus $status): array
    {
        $customer = Customer::factory()->create();
        $ticket = Ticket::factory()->for($customer)->openedByCustomer()->status($status)->create();

        return [$customer, $ticket];
    }
}
