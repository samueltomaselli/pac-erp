<?php

namespace Tests\Feature\Proposals;

use App\Enums\ProposalStatus;
use App\Models\Proposal;
use App\Models\ProposalAcceptance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PublicProposalAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-07 01:30:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Maria Cliente',
            'document' => '529.982.247-25',
            'email' => 'maria@example.com',
        ], $overrides);
    }

    public function test_valid_acceptance_accepts_and_records_who_accepted(): void
    {
        $proposal = Proposal::factory()->sent()->hasItems(1)->create(['valid_until' => '2026-10-07']);

        $this->withHeaders(['User-Agent' => 'Navegador do Cliente'])
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->postJson("/api/public/proposals/{$proposal->pub_id}/accept", $this->payload([
                'ip_address' => '1.1.1.1',
                'user_agent' => 'forjado',
            ]))
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.can_accept', false)
            ->assertJsonPath('data.acceptance.name', 'Maria Cliente');

        $proposal->refresh();
        $this->assertSame(ProposalStatus::Accepted, $proposal->status);
        $this->assertSame('2026-10-07 01:30:00', $proposal->decided_at->format('Y-m-d H:i:s'));

        $this->assertDatabaseHas('proposal_acceptances', [
            'proposal_id' => $proposal->id,
            'name' => 'Maria Cliente',
            'document' => '52998224725',
            'email' => 'maria@example.com',
            'ip_address' => '203.0.113.10',
            'user_agent' => 'Navegador do Cliente',
            'accepted_at' => '2026-10-07 01:30:00',
        ]);
    }

    public function test_acceptance_of_sent_proposal_past_validity_is_refused(): void
    {
        // O job ainda não rodou: status `sent`, prazo vencido ontem.
        $proposal = Proposal::factory()->sent()->hasItems(1)->create(['valid_until' => '2026-10-06']);

        $this->postJson("/api/public/proposals/{$proposal->pub_id}/accept", $this->payload())
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esta proposta não está disponível para aceite.');

        $this->assertSame(ProposalStatus::Sent, $proposal->fresh()->status);
        $this->assertDatabaseCount('proposal_acceptances', 0);
    }

    public function test_acceptance_of_expired_proposal_is_refused(): void
    {
        if (! defined(ProposalStatus::class.'::Expired')) {
            $this->markTestSkipped('ProposalStatus::Expired chega com o PR do vencimento automático.');
        }

        $proposal = Proposal::factory()->hasItems(1)->create([
            'status' => ProposalStatus::Expired,
            'valid_until' => '2026-10-01',
        ]);

        $this->postJson("/api/public/proposals/{$proposal->pub_id}/accept", $this->payload())
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esta proposta não está disponível para aceite.');

        $this->assertDatabaseCount('proposal_acceptances', 0);
    }

    public function test_acceptance_of_draft_proposal_returns_404(): void
    {
        $proposal = Proposal::factory()->draft()->hasItems(1)->create();

        $this->postJson("/api/public/proposals/{$proposal->pub_id}/accept", $this->payload())
            ->assertNotFound();

        $this->assertSame(ProposalStatus::Draft, $proposal->fresh()->status);
    }

    public function test_draft_returns_404_even_with_invalid_body(): void
    {
        $proposal = Proposal::factory()->draft()->hasItems(1)->create();

        $this->postJson("/api/public/proposals/{$proposal->pub_id}/accept", [])
            ->assertNotFound();
    }

    public function test_acceptance_of_rejected_proposal_returns_404(): void
    {
        $proposal = Proposal::factory()->rejected()->hasItems(1)->create();

        $this->postJson("/api/public/proposals/{$proposal->pub_id}/accept", $this->payload())
            ->assertNotFound();
    }

    public function test_second_acceptance_is_refused_and_does_not_overwrite_the_first(): void
    {
        $proposal = Proposal::factory()->sent()->hasItems(1)->create();

        $this->postJson("/api/public/proposals/{$proposal->pub_id}/accept", $this->payload())
            ->assertOk();

        $original = ProposalAcceptance::where('proposal_id', $proposal->id)->firstOrFail();

        Carbon::setTestNow('2026-10-07 02:00:00');

        $this->postJson("/api/public/proposals/{$proposal->pub_id}/accept", $this->payload([
            'name' => 'Outra Pessoa',
            'document' => '11.222.333/0001-81',
            'email' => 'outra@example.com',
        ]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esta proposta não está disponível para aceite.');

        $this->assertDatabaseCount('proposal_acceptances', 1);
        $this->assertEquals($original->getAttributes(), $original->fresh()->getAttributes());
    }

    public function test_invalid_document_is_refused_with_portuguese_message(): void
    {
        $proposal = Proposal::factory()->sent()->hasItems(1)->create();

        $this->postJson("/api/public/proposals/{$proposal->pub_id}/accept", $this->payload(['document' => '123.456.789-00']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['document' => 'O campo CNPJ/CPF deve ser um CPF ou CNPJ válido.']);

        $this->assertSame(ProposalStatus::Sent, $proposal->fresh()->status);
        $this->assertDatabaseCount('proposal_acceptances', 0);
    }

    public function test_missing_fields_are_refused(): void
    {
        $proposal = Proposal::factory()->sent()->hasItems(1)->create();

        $this->postJson("/api/public/proposals/{$proposal->pub_id}/accept", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'document', 'email']);

        $this->assertSame(ProposalStatus::Sent, $proposal->fresh()->status);
    }

    public function test_invalid_email_is_refused(): void
    {
        $proposal = Proposal::factory()->sent()->hasItems(1)->create();

        $this->postJson("/api/public/proposals/{$proposal->pub_id}/accept", $this->payload(['email' => 'nao-e-email']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_there_is_no_public_reject_route(): void
    {
        $proposal = Proposal::factory()->sent()->hasItems(1)->create();

        $status = $this->postJson("/api/public/proposals/{$proposal->pub_id}/reject")->status();

        $this->assertContains($status, [404, 405]);
        $this->assertSame(ProposalStatus::Sent, $proposal->fresh()->status);
    }
}
