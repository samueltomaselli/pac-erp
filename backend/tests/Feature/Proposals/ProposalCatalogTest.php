<?php

namespace Tests\Feature\Proposals;

use App\Enums\ProposalItemType;
use App\Models\Customer;
use App\Models\Proposal;
use App\Models\ProposalCatalogItem;
use App\Models\ProposalItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProposalCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_create_a_catalog_item_with_defaults(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/admin/proposal-catalog-items', [
            'name' => 'Assessoria mensal',
            'type' => ProposalItemType::Recurring->value,
            'default_unit_amount_cents' => 50000,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Assessoria mensal')
            ->assertJsonPath('data.type', 'recurring')
            ->assertJsonPath('data.type_label', 'Recorrente')
            ->assertJsonPath('data.default_quantity', 1)
            ->assertJsonPath('data.default_unit_amount_cents', 50000)
            ->assertJsonPath('data.allows_installments', false)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.sort_order', 0);

        $this->assertDatabaseHas('proposal_catalog_items', ['name' => 'Assessoria mensal', 'is_active' => true]);
    }

    public function test_a_catalog_item_requires_name_type_and_price(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/admin/proposal-catalog-items', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'type', 'default_unit_amount_cents']);
    }

    public function test_an_admin_can_show_update_and_delete_a_catalog_item(): void
    {
        $admin = User::factory()->admin()->create();
        $catalogItem = ProposalCatalogItem::factory()->create(['name' => 'Implantação']);

        $this->actingAs($admin)->getJson("/api/admin/proposal-catalog-items/{$catalogItem->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Implantação');

        $this->actingAs($admin)->putJson("/api/admin/proposal-catalog-items/{$catalogItem->id}", [
            'default_unit_amount_cents' => 120000,
            'is_active' => false,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.default_unit_amount_cents', 120000)
            ->assertJsonPath('data.is_active', false);

        $this->actingAs($admin)->deleteJson("/api/admin/proposal-catalog-items/{$catalogItem->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('proposal_catalog_items', ['id' => $catalogItem->id]);
    }

    public function test_the_listing_is_ordered_by_sort_order_then_name(): void
    {
        $admin = User::factory()->admin()->create();
        ProposalCatalogItem::factory()->create(['name' => 'Alfa', 'sort_order' => 2]);
        ProposalCatalogItem::factory()->create(['name' => 'Zeta', 'sort_order' => 1]);
        ProposalCatalogItem::factory()->create(['name' => 'Beta', 'sort_order' => 1]);

        $response = $this->actingAs($admin)->getJson('/api/admin/proposal-catalog-items');

        $response->assertStatus(200);
        $this->assertSame(['Beta', 'Zeta', 'Alfa'], array_column($response->json('data'), 'name'));
    }

    public function test_the_listing_can_be_filtered_by_active(): void
    {
        $admin = User::factory()->admin()->create();
        ProposalCatalogItem::factory()->create(['name' => 'Ativo']);
        ProposalCatalogItem::factory()->inactive()->create(['name' => 'Inativo']);

        $this->actingAs($admin)->getJson('/api/admin/proposal-catalog-items?active=1')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ativo');

        $this->actingAs($admin)->getJson('/api/admin/proposal-catalog-items')
            ->assertJsonCount(2, 'data');
    }

    public function test_an_item_created_from_the_catalog_inherits_its_defaults(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->create();
        $catalogItem = ProposalCatalogItem::factory()->oneTime()->create([
            'name' => 'Implantação',
            'default_quantity' => 2,
            'default_unit_amount_cents' => 100000,
        ]);

        $this->actingAs($admin)->postJson("/api/admin/proposals/{$proposal->id}/items", [
            'catalog_item_id' => $catalogItem->id,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.items.0.catalog_item_id', $catalogItem->id)
            ->assertJsonPath('data.items.0.description', 'Implantação')
            ->assertJsonPath('data.items.0.type', 'one_time')
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.unit_amount_cents', 100000)
            ->assertJsonPath('data.items.0.discount_cents', 0)
            ->assertJsonPath('data.items.0.installments', 1)
            ->assertJsonPath('data.totals.total_cents', 200000);
    }

    public function test_explicit_fields_override_the_catalog_defaults(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->create();
        $catalogItem = ProposalCatalogItem::factory()->create([
            'name' => 'Assessoria mensal',
            'default_quantity' => 1,
            'default_unit_amount_cents' => 50000,
        ]);

        $this->actingAs($admin)->postJson("/api/admin/proposals/{$proposal->id}/items", [
            'catalog_item_id' => $catalogItem->id,
            'description' => 'Assessoria mensal — plano anual',
            'unit_amount_cents' => 45000,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.items.0.description', 'Assessoria mensal — plano anual')
            ->assertJsonPath('data.items.0.type', 'recurring')
            ->assertJsonPath('data.items.0.quantity', 1)
            ->assertJsonPath('data.items.0.unit_amount_cents', 45000);
    }

    public function test_an_inactive_catalog_item_cannot_be_added(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->create();
        $catalogItem = ProposalCatalogItem::factory()->inactive()->create();

        $this->actingAs($admin)->postJson("/api/admin/proposals/{$proposal->id}/items", [
            'catalog_item_id' => $catalogItem->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Este item do catálogo está inativo.');

        $this->assertDatabaseCount('proposal_items', 0);
    }

    public function test_a_catalog_item_that_does_not_allow_installments_cannot_be_split(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->create();
        $catalogItem = ProposalCatalogItem::factory()->oneTime()->create(['allows_installments' => false]);

        $this->actingAs($admin)->postJson("/api/admin/proposals/{$proposal->id}/items", [
            'catalog_item_id' => $catalogItem->id,
            'installments' => 3,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Este item do catálogo não permite parcelamento.');

        $this->assertDatabaseCount('proposal_items', 0);
    }

    public function test_a_catalog_item_that_allows_installments_can_be_split(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->create();
        $catalogItem = ProposalCatalogItem::factory()->allowsInstallments()->create(['default_unit_amount_cents' => 90000]);

        $this->actingAs($admin)->postJson("/api/admin/proposals/{$proposal->id}/items", [
            'catalog_item_id' => $catalogItem->id,
            'installments' => 3,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.items.0.installments', 3)
            ->assertJsonPath('data.totals.installment_monthly_cents', 30000);
    }

    public function test_changing_the_catalog_price_does_not_touch_existing_items(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->create();
        $catalogItem = ProposalCatalogItem::factory()->create(['default_unit_amount_cents' => 50000]);

        $this->actingAs($admin)->postJson("/api/admin/proposals/{$proposal->id}/items", [
            'catalog_item_id' => $catalogItem->id,
        ])->assertStatus(201);

        $this->actingAs($admin)->putJson("/api/admin/proposal-catalog-items/{$catalogItem->id}", [
            'default_unit_amount_cents' => 99000,
        ])->assertStatus(200);

        $this->assertDatabaseHas('proposal_items', [
            'proposal_id' => $proposal->id,
            'catalog_item_id' => $catalogItem->id,
            'unit_amount_cents' => 50000,
        ]);
    }

    public function test_deleting_a_catalog_item_keeps_the_proposal_items(): void
    {
        $admin = User::factory()->admin()->create();
        $catalogItem = ProposalCatalogItem::factory()->create();
        $item = ProposalItem::factory()->for(Proposal::factory())->create(['catalog_item_id' => $catalogItem->id]);

        $this->actingAs($admin)->deleteJson("/api/admin/proposal-catalog-items/{$catalogItem->id}")
            ->assertStatus(200);

        $this->assertDatabaseHas('proposal_items', ['id' => $item->id, 'catalog_item_id' => null]);
    }

    public function test_a_customer_cannot_manage_the_catalog(): void
    {
        $customer = Customer::factory()->create();
        $catalogItem = ProposalCatalogItem::factory()->create();

        $this->actingAs($customer->user)->getJson('/api/admin/proposal-catalog-items')->assertStatus(403);

        $this->actingAs($customer->user)->postJson('/api/admin/proposal-catalog-items', [
            'name' => 'Assessoria',
            'type' => ProposalItemType::Recurring->value,
            'default_unit_amount_cents' => 50000,
        ])->assertStatus(403);

        $this->actingAs($customer->user)->deleteJson("/api/admin/proposal-catalog-items/{$catalogItem->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('proposal_catalog_items', ['id' => $catalogItem->id]);
    }
}
