<?php

namespace Tests\Feature\Proposals;

use App\Enums\ProposalItemType;
use App\Models\Customer;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProposalItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_add_an_item_and_the_totals_follow(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->create();

        $response = $this->actingAs($admin)->postJson("/api/admin/proposals/{$proposal->id}/items", [
            'type' => ProposalItemType::OneTime->value,
            'description' => 'Implantação',
            'quantity' => 2,
            'unit_amount_cents' => 25000,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.id', $proposal->id)
            ->assertJsonPath('data.customer.id', $proposal->customer_id)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.description', 'Implantação')
            ->assertJsonPath('data.items.0.type_label', 'Pontual')
            ->assertJsonPath('data.items.0.discount_cents', 0)
            ->assertJsonPath('data.items.0.installments', 1)
            ->assertJsonPath('data.items.0.line_total_cents', 50000)
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.totals.total_cents', 50000)
            ->assertJsonPath('data.totals.onetime_single_cents', 50000);

        $this->assertDatabaseHas('proposal_items', [
            'proposal_id' => $proposal->id,
            'description' => 'Implantação',
            'unit_amount_cents' => 25000,
        ]);
    }

    public function test_an_admin_can_add_an_installment_item(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->create();

        $this->actingAs($admin)->postJson("/api/admin/proposals/{$proposal->id}/items", [
            'type' => ProposalItemType::OneTime->value,
            'description' => 'Implantação',
            'quantity' => 1,
            'unit_amount_cents' => 100000,
            'installments' => 3,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.items.0.installment_amount_cents', 33333)
            ->assertJsonPath('data.items.0.last_installment_cents', 33334)
            ->assertJsonPath('data.totals.installment_monthly_cents', 33333)
            ->assertJsonPath('data.totals.max_installments', 3);
    }

    public function test_an_admin_can_update_an_item_and_the_totals_follow(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->create();
        $item = ProposalItem::factory()->for($proposal)->recurring()->create([
            'quantity' => 1,
            'unit_amount_cents' => 10000,
        ]);

        $this->actingAs($admin)->putJson("/api/admin/proposals/{$proposal->id}/items/{$item->id}", [
            'quantity' => 3,
            'discount_cents' => 5000,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.items.0.quantity', 3)
            ->assertJsonPath('data.items.0.line_total_cents', 25000)
            ->assertJsonPath('data.totals.mrr_cents', 25000)
            ->assertJsonPath('data.totals.total_cents', 25000);

        $this->assertDatabaseHas('proposal_items', ['id' => $item->id, 'quantity' => 3, 'discount_cents' => 5000]);
    }

    public function test_an_admin_can_remove_an_item_and_the_totals_follow(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->create();
        $kept = ProposalItem::factory()->for($proposal)->create(['quantity' => 1, 'unit_amount_cents' => 40000]);
        $removed = ProposalItem::factory()->for($proposal)->create(['quantity' => 1, 'unit_amount_cents' => 60000]);

        $this->actingAs($admin)->deleteJson("/api/admin/proposals/{$proposal->id}/items/{$removed->id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $kept->id)
            ->assertJsonPath('data.totals.total_cents', 40000);

        $this->assertDatabaseMissing('proposal_items', ['id' => $removed->id]);
    }

    public function test_a_discount_larger_than_the_item_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->create();

        $this->actingAs($admin)->postJson("/api/admin/proposals/{$proposal->id}/items", [
            'type' => ProposalItemType::OneTime->value,
            'description' => 'Consultoria',
            'quantity' => 2,
            'unit_amount_cents' => 10000,
            'discount_cents' => 20001,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'O desconto não pode ser maior que o valor do item.');

        $this->assertDatabaseCount('proposal_items', 0);
    }

    public function test_a_discount_larger_than_the_item_is_rejected_on_update(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->create();
        $item = ProposalItem::factory()->for($proposal)->create(['quantity' => 2, 'unit_amount_cents' => 10000]);

        $this->actingAs($admin)->putJson("/api/admin/proposals/{$proposal->id}/items/{$item->id}", [
            'quantity' => 1,
            'discount_cents' => 15000,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'O desconto não pode ser maior que o valor do item.');

        $this->assertDatabaseHas('proposal_items', ['id' => $item->id, 'quantity' => 2, 'discount_cents' => 0]);
    }

    public function test_quantity_must_be_at_least_one(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->create();

        foreach ([0, -1] as $quantity) {
            $this->actingAs($admin)->postJson("/api/admin/proposals/{$proposal->id}/items", [
                'type' => ProposalItemType::OneTime->value,
                'description' => 'Consultoria',
                'quantity' => $quantity,
                'unit_amount_cents' => 10000,
            ])
                ->assertStatus(422)
                ->assertJsonValidationErrors('quantity');
        }

        $this->assertDatabaseCount('proposal_items', 0);
    }

    public function test_description_is_required(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->create();

        $this->actingAs($admin)->postJson("/api/admin/proposals/{$proposal->id}/items", [
            'type' => ProposalItemType::OneTime->value,
            'description' => '',
            'quantity' => 1,
            'unit_amount_cents' => 10000,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('description');
    }

    public function test_amounts_must_be_integer_cents(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->create();

        foreach ([10.5, '1.500,00'] as $amount) {
            $this->actingAs($admin)->postJson("/api/admin/proposals/{$proposal->id}/items", [
                'type' => ProposalItemType::OneTime->value,
                'description' => 'Consultoria',
                'quantity' => 1,
                'unit_amount_cents' => $amount,
            ])
                ->assertStatus(422)
                ->assertJsonValidationErrors('unit_amount_cents');
        }
    }

    public function test_a_recurring_item_cannot_be_split_into_installments(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->create();

        $this->actingAs($admin)->postJson("/api/admin/proposals/{$proposal->id}/items", [
            'type' => ProposalItemType::Recurring->value,
            'description' => 'Mensalidade',
            'quantity' => 1,
            'unit_amount_cents' => 50000,
            'installments' => 3,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Item recorrente não pode ser parcelado.');

        $this->assertDatabaseCount('proposal_items', 0);
    }

    public function test_an_installment_item_cannot_become_recurring(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->create();
        $item = ProposalItem::factory()->for($proposal)->installments(3)->create();

        $this->actingAs($admin)->putJson("/api/admin/proposals/{$proposal->id}/items/{$item->id}", [
            'type' => ProposalItemType::Recurring->value,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Item recorrente não pode ser parcelado.');

        $this->assertSame(ProposalItemType::OneTime, $item->fresh()->type);
    }

    public function test_an_item_from_another_proposal_is_not_found(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->create();
        $foreignItem = ProposalItem::factory()->for(Proposal::factory())->create(['quantity' => 1]);

        $this->actingAs($admin)->putJson("/api/admin/proposals/{$proposal->id}/items/{$foreignItem->id}", [
            'quantity' => 5,
        ])->assertStatus(404);

        $this->actingAs($admin)->deleteJson("/api/admin/proposals/{$proposal->id}/items/{$foreignItem->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('proposal_items', ['id' => $foreignItem->id, 'quantity' => 1]);
    }

    public static function lockedStates(): array
    {
        return [
            'enviada' => ['sent'],
            'aceita' => ['accepted'],
            'recusada' => ['rejected'],
        ];
    }

    #[DataProvider('lockedStates')]
    public function test_a_proposal_that_is_not_a_draft_refuses_item_changes(string $state): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->{$state}()->create();
        $item = ProposalItem::factory()->for($proposal)->create(['quantity' => 1, 'unit_amount_cents' => 10000]);
        $message = 'Somente propostas em rascunho podem ter itens alterados.';

        $this->actingAs($admin)->postJson("/api/admin/proposals/{$proposal->id}/items", [
            'type' => ProposalItemType::OneTime->value,
            'description' => 'Extra',
            'quantity' => 1,
            'unit_amount_cents' => 10000,
        ])->assertStatus(422)->assertJsonPath('message', $message);

        $this->actingAs($admin)->putJson("/api/admin/proposals/{$proposal->id}/items/{$item->id}", [
            'quantity' => 9,
        ])->assertStatus(422)->assertJsonPath('message', $message);

        $this->actingAs($admin)->deleteJson("/api/admin/proposals/{$proposal->id}/items/{$item->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', $message);

        $this->assertDatabaseCount('proposal_items', 1);
        $this->assertDatabaseHas('proposal_items', ['id' => $item->id, 'quantity' => 1]);
    }

    public function test_a_customer_cannot_manage_items(): void
    {
        $customer = Customer::factory()->create();
        $proposal = Proposal::factory()->for($customer)->create();
        $item = ProposalItem::factory()->for($proposal)->create(['quantity' => 1]);

        $this->actingAs($customer->user)->postJson("/api/admin/proposals/{$proposal->id}/items", [
            'type' => ProposalItemType::OneTime->value,
            'description' => 'Extra',
            'quantity' => 1,
            'unit_amount_cents' => 10000,
        ])->assertStatus(403);

        $this->actingAs($customer->user)->putJson("/api/admin/proposals/{$proposal->id}/items/{$item->id}", [
            'quantity' => 9,
        ])->assertStatus(403);

        $this->actingAs($customer->user)->deleteJson("/api/admin/proposals/{$proposal->id}/items/{$item->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('proposal_items', ['id' => $item->id, 'quantity' => 1]);
    }
}
