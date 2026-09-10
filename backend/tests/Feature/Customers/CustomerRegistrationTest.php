<?php

namespace Tests\Feature\Customers;

use App\Enums\CustomerSegment;
use App\Enums\CustomerStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Database\Factories\CustomerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_register_a_customer_with_all_registration_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/admin/customers', [
            'name' => 'Imobiliária Alvorada',
            'document' => '11.222.333/0001-81',
            'email' => 'contato@alvorada.com.br',
            'phone' => '(11) 98888-7777',
            'contact_name' => 'Marina Alves',
            'segment' => CustomerSegment::RealEstate->value,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Imobiliária Alvorada');
        $response->assertJsonPath('data.segment', CustomerSegment::RealEstate->value);
        $response->assertJsonPath('data.segment_label', 'Imobiliária');
        $response->assertJsonPath('data.status', CustomerStatus::Active->value);
        $response->assertJsonPath('data.contact_name', 'Marina Alves');

        $this->assertDatabaseHas('customers', [
            'name' => 'Imobiliária Alvorada',
            'document' => '11222333000181',
            'email' => 'contato@alvorada.com.br',
            'status' => CustomerStatus::Active->value,
        ]);
    }

    public function test_registering_a_customer_creates_a_login_credential_in_the_customer_role(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/admin/customers', [
            'name' => 'Corban Prata',
            'document' => '52998224725',
            'email' => 'financeiro@prata.com.br',
            'segment' => CustomerSegment::Corban->value,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'financeiro@prata.com.br',
            'role' => UserRole::Customer->value,
        ]);

        $customer = Customer::firstOrFail();
        $this->assertNotNull($customer->user);
        $this->assertSame(UserRole::Customer, $customer->user->role);
    }

    public function test_the_generated_password_is_returned_once_and_stored_hashed(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/admin/customers', [
            'name' => 'Corban Prata',
            'document' => '52998224725',
            'email' => 'financeiro@prata.com.br',
            'segment' => CustomerSegment::Corban->value,
        ]);

        $password = $response->json('generated_password');

        $this->assertIsString($password);
        $this->assertNotEmpty($password);

        $user = User::where('email', 'financeiro@prata.com.br')->firstOrFail();

        $this->assertNotSame($password, $user->password);
        $this->assertTrue(Hash::check($password, $user->password));

        $customer = Customer::firstOrFail();
        $show = $this->actingAs($admin)->getJson("/api/admin/customers/{$customer->id}");
        $show->assertStatus(200);
        $this->assertNull($show->json('generated_password'));
        $show->assertJsonMissingPath('data.user.password');
    }

    public function test_the_segment_must_be_one_of_the_supported_values(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/admin/customers', [
            'name' => 'Corban Prata',
            'document' => '52998224725',
            'email' => 'financeiro@prata.com.br',
            'segment' => 'banco',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('segment');
    }

    public function test_every_supported_segment_is_accepted(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (CustomerSegment::cases() as $index => $segment) {
            $response = $this->actingAs($admin)->postJson('/api/admin/customers', [
                'name' => 'Cliente '.$segment->value,
                'document' => CustomerFactory::cnpj(),
                'email' => "cliente{$index}@example.com",
                'segment' => $segment->value,
            ]);

            $response->assertStatus(201);
            $response->assertJsonPath('data.segment', $segment->value);
        }
    }

    public function test_registration_rejects_an_invalid_document(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/admin/customers', [
            'name' => 'Corban Prata',
            'document' => '11.222.333/0001-80',
            'email' => 'financeiro@prata.com.br',
            'segment' => CustomerSegment::Corban->value,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('document');
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_registration_rejects_a_duplicate_document_even_when_punctuated_differently(): void
    {
        $admin = User::factory()->admin()->create();
        Customer::factory()->create(['document' => '11222333000181']);

        $response = $this->actingAs($admin)->postJson('/api/admin/customers', [
            'name' => 'Outro Cliente',
            'document' => '11.222.333/0001-81',
            'email' => 'outro@example.com',
            'segment' => CustomerSegment::Other->value,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('document');
    }

    public function test_registration_rejects_an_email_already_used_by_another_login(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->actingAs($admin)->postJson('/api/admin/customers', [
            'name' => 'Corban Prata',
            'document' => '52998224725',
            'email' => 'taken@example.com',
            'segment' => CustomerSegment::Corban->value,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_registration_rejects_missing_required_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/admin/customers', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'document', 'email', 'segment']);
    }

    public function test_no_credential_is_left_behind_when_the_customer_cannot_be_created(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/admin/customers', [
            'name' => 'Corban Prata',
            'document' => 'invalido',
            'email' => 'financeiro@prata.com.br',
            'segment' => CustomerSegment::Corban->value,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('users', ['email' => 'financeiro@prata.com.br']);
    }

    public function test_a_customer_cannot_register_other_customers(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->postJson('/api/admin/customers', [
            'name' => 'Corban Prata',
            'document' => '52998224725',
            'email' => 'financeiro@prata.com.br',
            'segment' => CustomerSegment::Corban->value,
        ])->assertStatus(403);
    }

    public function test_guests_cannot_register_customers(): void
    {
        $this->postJson('/api/admin/customers', [])->assertStatus(401);
    }
}
