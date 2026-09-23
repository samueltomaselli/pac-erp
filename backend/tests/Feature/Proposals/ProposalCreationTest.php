<?php

namespace Tests\Feature\Proposals;

use App\Enums\PaymentMethod;
use App\Enums\ProposalStatus;
use App\Models\Customer;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProposalCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_create_a_proposal_for_a_customer(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/admin/proposals', [
            'customer_id' => $customer->id,
            'title' => 'Assessoria de crédito',
            'issued_on' => '2026-09-20',
            'valid_until' => '2026-10-20',
            'payment_method' => PaymentMethod::Pix->value,
            'observations' => 'Implantação em quinze dias.',
            'terms' => 'Vigência de doze meses.',
            'status' => ProposalStatus::Accepted->value,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', ProposalStatus::Draft->value)
            ->assertJsonPath('data.payment_method_label', 'Pix')
            ->assertJsonPath('data.reference', '#PC-'.Proposal::query()->first()->id)
            ->assertJsonMissingPath('data.pub_id');

        $this->assertDatabaseHas('proposals', [
            'customer_id' => $customer->id,
            'created_by' => $admin->id,
            'status' => ProposalStatus::Draft->value,
            'observations' => 'Implantação em quinze dias.',
        ]);
    }

    public function test_invalid_dates_and_inactive_customers_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($admin)->postJson('/api/admin/proposals', [
            'customer_id' => $customer->id,
            'title' => 'Prazo inválido',
            'issued_on' => '2026-09-20',
            'valid_until' => '2026-09-19',
        ])->assertUnprocessable()->assertJsonValidationErrors('valid_until');

        $customer->delete();

        $this->actingAs($admin)->postJson('/api/admin/proposals', [
            'customer_id' => $customer->id,
            'title' => 'Cliente inativo',
            'issued_on' => '2026-09-20',
            'valid_until' => '2026-09-20',
        ])->assertUnprocessable()->assertJsonValidationErrors('customer_id');
    }

    public function test_a_customer_cannot_manage_proposals(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)->getJson('/api/admin/proposals')->assertForbidden();
    }
}
