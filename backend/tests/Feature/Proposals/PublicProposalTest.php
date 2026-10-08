<?php

namespace Tests\Feature\Proposals;

use App\Enums\ProposalStatus;
use App\Models\Proposal;
use App\Models\ProposalAcceptance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicProposalTest extends TestCase
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

    public function test_unknown_pub_id_returns_404(): void
    {
        $this->getJson('/api/public/proposals/'.Str::uuid())->assertNotFound();
    }

    public function test_sequential_id_does_not_resolve_the_proposal(): void
    {
        $proposal = Proposal::factory()->sent()->hasItems(1)->create();

        $this->getJson("/api/public/proposals/{$proposal->id}")->assertNotFound();
    }

    public function test_draft_proposal_returns_404(): void
    {
        $proposal = Proposal::factory()->draft()->hasItems(1)->create();

        $this->getJson("/api/public/proposals/{$proposal->pub_id}")->assertNotFound();
    }

    public function test_rejected_proposal_returns_404(): void
    {
        $proposal = Proposal::factory()->rejected()->hasItems(1)->create();

        $this->getJson("/api/public/proposals/{$proposal->pub_id}")->assertNotFound();
    }

    public function test_sent_proposal_within_validity_is_pending(): void
    {
        $proposal = Proposal::factory()->sent()->hasItems(2)->create(['valid_until' => '2026-10-07']);

        $this->getJson("/api/public/proposals/{$proposal->pub_id}")
            ->assertOk()
            ->assertJsonPath('data.title', $proposal->title)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.status_label', 'Pendente')
            ->assertJsonPath('data.can_accept', true)
            ->assertJsonPath('data.customer.name', $proposal->customer->name)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.acceptance', null);
    }

    public function test_sent_proposal_past_validity_is_expired_before_the_job_runs(): void
    {
        // 01:30, o job de vencimento só roda às 03:00: o banco ainda diz `sent`.
        $proposal = Proposal::factory()->sent()->hasItems(1)->create(['valid_until' => '2026-10-06']);

        $this->getJson("/api/public/proposals/{$proposal->pub_id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'expired')
            ->assertJsonPath('data.status_label', 'Expirada')
            ->assertJsonPath('data.can_accept', false);

        $this->assertSame(ProposalStatus::Sent, $proposal->fresh()->status);
    }

    public function test_expired_proposal_is_expired(): void
    {
        $this->skipUnlessExpiredStatusExists();

        $proposal = Proposal::factory()->hasItems(1)->create([
            'status' => ProposalStatus::Expired,
            'valid_until' => '2026-10-01',
        ]);

        $this->getJson("/api/public/proposals/{$proposal->pub_id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'expired')
            ->assertJsonPath('data.can_accept', false);
    }

    public function test_accepted_proposal_shows_acceptance_name_and_date_only(): void
    {
        $proposal = Proposal::factory()->accepted()->hasItems(1)->create();
        ProposalAcceptance::create([
            'proposal_id' => $proposal->id,
            'name' => 'Maria Cliente',
            'document' => '52998224725',
            'email' => 'maria@example.com',
            'ip_address' => '203.0.113.10',
            'user_agent' => 'Mozilla/5.0',
            'accepted_at' => '2026-10-06 14:00:00',
        ]);

        $this->getJson("/api/public/proposals/{$proposal->pub_id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.status_label', 'Aceita')
            ->assertJsonPath('data.can_accept', false)
            ->assertJsonPath('data.acceptance.name', 'Maria Cliente')
            ->assertJsonPath('data.acceptance.accepted_at', Carbon::parse('2026-10-06 14:00:00')->toIso8601String())
            ->assertJsonMissingPath('data.acceptance.document')
            ->assertJsonMissingPath('data.acceptance.email')
            ->assertJsonMissingPath('data.acceptance.ip_address')
            ->assertJsonMissingPath('data.acceptance.user_agent')
            ->assertJsonMissingPath('data.acceptance.id')
            ->assertJsonMissingPath('data.acceptance.proposal_id');
    }

    public function test_payload_does_not_leak_internal_fields(): void
    {
        $proposal = Proposal::factory()->sent()->hasItems(1)->create();

        $response = $this->getJson("/api/public/proposals/{$proposal->pub_id}")->assertOk();

        foreach ([
            'id',
            'pub_id',
            'customer_id',
            'created_by',
            'sent_at',
            'decided_at',
            'created_at',
            'updated_at',
            'is_editable',
            'allowed_transitions',
            'customer.id',
            'customer.document',
            'customer.email',
            'customer.phone',
            'customer.contact_name',
            'customer.user_id',
            'items.0.id',
            'items.0.proposal_id',
            'items.0.catalog_item_id',
        ] as $path) {
            $response->assertJsonMissingPath("data.{$path}");
        }

        // O status exposto é o público, nunca o interno.
        $this->assertNotSame($proposal->status->value, $response->json('data.status'));
        $this->assertStringNotContainsString($proposal->pub_id, $response->getContent());
        $this->assertStringNotContainsString($proposal->customer->document, $response->getContent());
        $this->assertStringNotContainsString($proposal->customer->email, $response->getContent());
    }

    public function test_route_works_without_any_session(): void
    {
        $proposal = Proposal::factory()->sent()->hasItems(1)->create();

        $this->assertGuest();
        $this->getJson("/api/public/proposals/{$proposal->pub_id}")->assertOk();
    }

    private function skipUnlessExpiredStatusExists(): void
    {
        if (! defined(ProposalStatus::class.'::Expired')) {
            $this->markTestSkipped('ProposalStatus::Expired chega com o PR do vencimento automático.');
        }
    }
}
