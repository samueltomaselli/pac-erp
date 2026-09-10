<?php

namespace Tests\Feature\Customers;

use App\Enums\CustomerSegment;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_edit_a_customer(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create([
            'name' => 'Nome Antigo',
            'segment' => CustomerSegment::Other,
            'phone' => '(11) 1111-1111',
        ]);

        $response = $this->actingAs($admin)->putJson("/api/admin/customers/{$customer->id}", [
            'name' => 'Nome Novo',
            'segment' => CustomerSegment::Corban->value,
            'phone' => '(11) 2222-2222',
            'contact_name' => 'João Souza',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Nome Novo');
        $response->assertJsonPath('data.segment', CustomerSegment::Corban->value);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Nome Novo',
            'phone' => '(11) 2222-2222',
            'contact_name' => 'João Souza',
        ]);
    }

    public function test_editing_the_email_keeps_the_login_credential_in_sync(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create(['email' => 'antigo@example.com']);

        $this->actingAs($admin)->putJson("/api/admin/customers/{$customer->id}", [
            'email' => 'novo@example.com',
            'name' => 'Razão Social Nova',
        ])->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $customer->user_id,
            'email' => 'novo@example.com',
            'name' => 'Razão Social Nova',
        ]);
    }

    public function test_a_customer_can_be_moved_between_statuses(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);

        $this->actingAs($admin)->putJson("/api/admin/customers/{$customer->id}", [
            'status' => CustomerStatus::Inactive->value,
        ])->assertStatus(200)->assertJsonPath('data.status', CustomerStatus::Inactive->value);
    }

    public function test_editing_normalises_a_punctuated_document(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($admin)->putJson("/api/admin/customers/{$customer->id}", [
            'document' => '529.982.247-25',
        ])->assertStatus(200)->assertJsonPath('data.document', '52998224725');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'document' => '52998224725',
        ]);
    }

    public function test_a_customer_keeps_its_own_document_on_edit(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create(['document' => '11222333000181']);

        $this->actingAs($admin)->putJson("/api/admin/customers/{$customer->id}", [
            'document' => '11222333000181',
            'name' => 'Mesmo Documento',
        ])->assertStatus(200);
    }

    public function test_editing_rejects_a_document_that_belongs_to_another_customer(): void
    {
        $admin = User::factory()->admin()->create();
        Customer::factory()->create(['document' => '11222333000181']);
        $customer = Customer::factory()->create();

        $response = $this->actingAs($admin)->putJson("/api/admin/customers/{$customer->id}", [
            'document' => '11222333000181',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('document');
    }

    public function test_editing_rejects_an_invalid_document(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($admin)->putJson("/api/admin/customers/{$customer->id}", [
            'document' => '11222333000180',
        ])->assertStatus(422)->assertJsonValidationErrors('document');
    }

    public function test_a_customer_cannot_edit_customers(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->putJson("/api/admin/customers/{$customer->id}", ['name' => 'Hackeado'])
            ->assertStatus(403);

        $this->assertDatabaseMissing('customers', ['name' => 'Hackeado']);
    }
}
