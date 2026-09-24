<?php

namespace Tests\Feature\Proposals;

use App\Enums\ProposalStatus;
use App\Models\Customer;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProposalStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_can_be_sent(): void
    {
        Carbon::setTestNow('2026-09-20 12:00:00');

        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->draft()->hasItems(1)->create();

        $response = $this->actingAs($admin)->postJson("/api/admin/proposals/{$proposal->id}/send");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', ProposalStatus::Sent->value)
            ->assertJsonPath('data.status_label', 'Enviada');

        $this->assertDatabaseHas('proposals', [
            'id' => $proposal->id,
            'status' => ProposalStatus::Sent->value,
        ]);
        $this->assertSame('2026-09-20 12:00:00', $proposal->fresh()->sent_at->format('Y-m-d H:i:s'));
        $this->assertNull($proposal->fresh()->decided_at);

        Carbon::setTestNow();
    }

    public function test_sent_can_be_accepted(): void
    {
        Carbon::setTestNow('2026-09-21 09:30:00');

        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->sent()->hasItems(1)->create([
            'sent_at' => '2026-09-20 12:00:00',
        ]);

        $response = $this->actingAs($admin)->postJson("/api/admin/proposals/{$proposal->id}/accept");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', ProposalStatus::Accepted->value)
            ->assertJsonPath('data.status_label', 'Aceita');

        $this->assertDatabaseHas('proposals', [
            'id' => $proposal->id,
            'status' => ProposalStatus::Accepted->value,
        ]);
        $this->assertSame('2026-09-20 12:00:00', $proposal->fresh()->sent_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-21 09:30:00', $proposal->fresh()->decided_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_sent_can_be_rejected(): void
    {
        Carbon::setTestNow('2026-09-21 17:45:00');

        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->sent()->hasItems(1)->create([
            'sent_at' => '2026-09-20 12:00:00',
        ]);

        $response = $this->actingAs($admin)->postJson("/api/admin/proposals/{$proposal->id}/reject");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', ProposalStatus::Rejected->value)
            ->assertJsonPath('data.status_label', 'Recusada');

        $this->assertDatabaseHas('proposals', [
            'id' => $proposal->id,
            'status' => ProposalStatus::Rejected->value,
        ]);
        $this->assertSame('2026-09-20 12:00:00', $proposal->fresh()->sent_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-21 17:45:00', $proposal->fresh()->decided_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    /**
     * @dataProvider invalidTransitionProvider
     */
    public function test_invalid_transitions_are_rejected(string $from, string $to): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->state(['status' => ProposalStatus::from($from)])->create();
        $before = $proposal->status->value;

        $route = match ($to) {
            'sent' => 'send',
            'accepted' => 'accept',
            'rejected' => 'reject',
        };

        $response = $this->actingAs($admin)->postJson("/api/admin/proposals/{$proposal->id}/{$route}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Não é possível mudar de '.ProposalStatus::from($from)->label().' para '.ProposalStatus::from($to)->label().'.');

        $this->assertSame($before, $proposal->fresh()->status->value);
    }

    public static function invalidTransitionProvider(): array
    {
        return [
            ['draft', 'accepted'],
            ['draft', 'rejected'],
            ['sent', 'sent'],
            ['accepted', 'sent'],
            ['accepted', 'rejected'],
            ['rejected', 'sent'],
            ['rejected', 'accepted'],
        ];
    }

    public function test_sending_a_proposal_without_items_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->draft()->create();

        $response = $this->actingAs($admin)->postJson("/api/admin/proposals/{$proposal->id}/send");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Não é possível enviar uma proposta sem itens.');

        $this->assertSame(ProposalStatus::Draft->value, $proposal->fresh()->status->value);
    }

    public function test_status_is_not_accepted_in_update_payload(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->draft()->create();

        $response = $this->actingAs($admin)->putJson("/api/admin/proposals/{$proposal->id}", [
            'status' => ProposalStatus::Accepted->value,
            'title' => 'Título atualizado',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Título atualizado');

        $this->assertSame(ProposalStatus::Draft->value, $proposal->fresh()->status->value);
    }

    public function test_status_is_not_editable_when_sent(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->sent()->hasItems(1)->create();

        $this->actingAs($admin)->putJson("/api/admin/proposals/{$proposal->id}", [
            'title' => 'Tentativa de edição',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Somente propostas em rascunho podem ser editadas.');
    }

    public function test_accepted_proposal_cannot_be_removed(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->accepted()->create();

        $this->actingAs($admin)->deleteJson("/api/admin/proposals/{$proposal->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Somente propostas em rascunho podem ser removidas.');

        $this->assertDatabaseHas('proposals', ['id' => $proposal->id]);
    }

    public function test_allowed_transitions_are_exposed_in_resource(): void
    {
        $admin = User::factory()->admin()->create();

        $draftProposal = Proposal::factory()->draft()->create();
        $sentProposal = Proposal::factory()->sent()->create();
        $acceptedProposal = Proposal::factory()->accepted()->create();
        $rejectedProposal = Proposal::factory()->rejected()->create();

        $this->actingAs($admin)->getJson("/api/admin/proposals/{$draftProposal->id}")
            ->assertJsonPath('data.allowed_transitions.0.status', 'sent')
            ->assertJsonPath('data.allowed_transitions.0.label', 'Enviada');

        $this->actingAs($admin)->getJson("/api/admin/proposals/{$sentProposal->id}")
            ->assertJsonPath('data.allowed_transitions.0.status', 'accepted')
            ->assertJsonPath('data.allowed_transitions.0.label', 'Aceita')
            ->assertJsonPath('data.allowed_transitions.1.status', 'rejected')
            ->assertJsonPath('data.allowed_transitions.1.label', 'Recusada');

        $this->actingAs($admin)->getJson("/api/admin/proposals/{$acceptedProposal->id}")
            ->assertJsonPath('data.allowed_transitions', []);

        $this->actingAs($admin)->getJson("/api/admin/proposals/{$rejectedProposal->id}")
            ->assertJsonPath('data.allowed_transitions', []);
    }

    public function test_is_editable_only_for_draft_status(): void
    {
        $admin = User::factory()->admin()->create();

        $draftProposal = Proposal::factory()->draft()->create();
        $sentProposal = Proposal::factory()->sent()->create();

        $this->actingAs($admin)->getJson("/api/admin/proposals/{$draftProposal->id}")
            ->assertJsonPath('data.is_editable', true);

        $this->actingAs($admin)->getJson("/api/admin/proposals/{$sentProposal->id}")
            ->assertJsonPath('data.is_editable', false);
    }

    public function test_customer_cannot_transition_statuses(): void
    {
        $customer = Customer::factory()->create();
        $proposal = Proposal::factory()->draft()->create();

        $this->actingAs($customer->user)->postJson("/api/admin/proposals/{$proposal->id}/send")
            ->assertStatus(403);

        $this->actingAs($customer->user)->postJson("/api/admin/proposals/{$proposal->id}/accept")
            ->assertStatus(403);

        $this->actingAs($customer->user)->postJson("/api/admin/proposals/{$proposal->id}/reject")
            ->assertStatus(403);
    }
}
