<?php

namespace Tests\Feature\Proposals;

use App\Enums\ProposalStatus;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProposalPublicLinkAccessTest extends TestCase
{
    use RefreshDatabase;

    private function sentProposal(): Proposal
    {
        $proposal = Proposal::factory()->create([
            'status' => ProposalStatus::Sent,
            'valid_until' => today()->addDays(5),
            'sent_at' => now(),
        ]);
        ProposalItem::factory()->for($proposal)->create();

        return $proposal;
    }

    public function test_the_admin_receives_the_public_id_so_the_link_can_be_shared(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = $this->sentProposal();

        $this->actingAs($admin)->getJson("/api/admin/proposals/{$proposal->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.pub_id', $proposal->pub_id);
    }

    public function test_a_draft_exposes_no_public_id_because_it_has_no_public_page(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->create(['status' => ProposalStatus::Draft]);

        $this->actingAs($admin)->getJson("/api/admin/proposals/{$proposal->id}")
            ->assertStatus(200)
            ->assertJsonMissingPath('data.pub_id');
    }

    public function test_the_public_page_shows_the_proposal_number(): void
    {
        $proposal = $this->sentProposal();

        $this->getJson("/api/public/proposals/{$proposal->pub_id}")
            ->assertStatus(200)
            ->assertJsonPath('data.reference', '#PC-'.$proposal->id);
    }

    public function test_the_public_payload_still_hides_every_internal_field(): void
    {
        $proposal = $this->sentProposal();

        $response = $this->getJson("/api/public/proposals/{$proposal->pub_id}")->assertStatus(200);

        foreach (['id', 'pub_id', 'customer_id', 'created_by', 'sent_at', 'decided_at', 'is_editable', 'allowed_transitions'] as $field) {
            $response->assertJsonMissingPath("data.{$field}");
        }
    }
}
