<?php

namespace Tests\Feature\Customers;

use App\Enums\ProposalStatus;
use App\Models\Customer;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_current_plan_is_the_latest_accepted_proposal_by_decision_date(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        $latest = Proposal::factory()->for($customer)->accepted()->create([
            'decided_at' => '2026-09-20 10:00:00',
            'valid_until' => '2026-09-01',
        ]);
        ProposalItem::factory()->for($latest)->recurring()->create([
            'unit_amount_cents' => 50000,
        ]);
        $older = Proposal::factory()->for($customer)->accepted()->create([
            'decided_at' => '2026-09-19 10:00:00',
        ]);
        ProposalItem::factory()->for($older)->recurring()->create([
            'unit_amount_cents' => 20000,
        ]);
        Proposal::factory()->for($customer)->sent()->create([
            'decided_at' => null,
            'issued_on' => '2026-09-30',
        ]);

        $this->actingAs($admin)
            ->getJson('/api/admin/customers/'.$customer->id)
            ->assertOk()
            ->assertJsonPath('data.current_plan.reference', $latest->reference)
            ->assertJsonPath('data.current_plan.title', $latest->title)
            ->assertJsonPath('data.current_plan.mrr_cents', 50000)
            ->assertJsonPath('data.current_plan.accepted_at', '2026-09-20T10:00:00-03:00');
    }

    public function test_only_accepted_proposals_can_be_the_current_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        foreach ([ProposalStatus::Sent, ProposalStatus::Rejected, ProposalStatus::Draft] as $status) {
            Proposal::factory()->for($customer)->create(['status' => $status]);
        }

        $this->actingAs($admin)
            ->getJson('/api/admin/customers/'.$customer->id)
            ->assertOk()
            ->assertJsonPath('data.current_plan', null);
    }

    public function test_a_customer_cannot_access_the_admin_customer_detail(): void
    {
        $customerUser = User::factory()->create();
        $customer = Customer::factory()->for($customerUser, 'user')->create();

        $this->actingAs($customerUser)
            ->getJson('/api/admin/customers/'.$customer->id)
            ->assertForbidden();
    }
}
