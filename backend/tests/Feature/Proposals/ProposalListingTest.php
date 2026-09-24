<?php

namespace Tests\Feature\Proposals;

use App\Enums\ProposalStatus;
use App\Models\Customer;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProposalListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_filter_and_search_proposals(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $proposal = Proposal::factory()->for($customer)->withItems()->create([
            'title' => 'Assessoria de crédito',
            'status' => ProposalStatus::Sent,
        ]);
        Proposal::factory()->for($otherCustomer)->create(['title' => 'Outro serviço']);

        $this->actingAs($admin)->getJson('/api/admin/proposals?customer_id='.$customer->id.'&status=sent&search=%23PC-'.$proposal->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $proposal->id)
            ->assertJsonPath('data.0.items_count', 2);
    }

    public function test_the_listing_is_paginated(): void
    {
        $admin = User::factory()->admin()->create();
        Proposal::factory()->count(3)->create();

        $this->actingAs($admin)->getJson('/api/admin/proposals?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);
    }
}
