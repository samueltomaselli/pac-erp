<?php

namespace Tests\Feature\Proposals;

use App\Models\Customer;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProposalIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_proposal_gets_a_unique_public_id_hidden_from_the_api(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        $first = Proposal::factory()->for($customer)->create();
        $second = Proposal::factory()->for($customer)->create();

        $this->assertNotSame($first->pub_id, $second->pub_id);
        $this->assertSame('#PC-'.$first->id, $first->reference);

        $this->actingAs($admin)->getJson('/api/admin/proposals/'.$first->id)
            ->assertOk()
            ->assertJsonPath('data.reference', '#PC-'.$first->id)
            ->assertJsonMissingPath('data.pub_id');
    }
}
