<?php

namespace Tests\Feature\Customers;

use App\Enums\ProposalStatus;
use App\Models\Customer;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerProposalHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_customer_detail_returns_its_proposals_in_stable_reverse_chronological_order(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $older = Proposal::factory()->for($customer)->create([
            'issued_on' => '2026-09-20',
            'status' => ProposalStatus::Sent,
        ]);
        $sameDayFirst = Proposal::factory()->for($customer)->create([
            'issued_on' => '2026-09-25',
            'status' => ProposalStatus::Sent,
        ]);
        $sameDayLast = Proposal::factory()->for($customer)->create([
            'issued_on' => '2026-09-25',
            'status' => ProposalStatus::Sent,
        ]);
        Proposal::factory()->for($otherCustomer)->create([
            'issued_on' => '2026-09-26',
            'status' => ProposalStatus::Sent,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/admin/customers/'.$customer->id)
            ->assertOk()
            ->assertJsonPath('data.proposals_count', 3)
            ->assertJsonPath('data.proposals.0.id', $sameDayLast->id)
            ->assertJsonPath('data.proposals.1.id', $sameDayFirst->id)
            ->assertJsonPath('data.proposals.2.id', $older->id);
    }

    public function test_a_customer_without_proposals_returns_an_empty_history(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($admin)
            ->getJson('/api/admin/customers/'.$customer->id)
            ->assertOk()
            ->assertJsonPath('data.proposals', [])
            ->assertJsonPath('data.proposals_count', 0)
            ->assertJsonPath('data.current_plan', null);
    }
}
