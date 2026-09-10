<?php

namespace Tests\Feature\Localization;

use App\Enums\CustomerSegment;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidationMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_speaks_brazilian_portuguese(): void
    {
        $this->assertSame('pt_BR', config('app.locale'));
    }

    public function test_a_required_field_reports_in_portuguese_using_the_business_field_name(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/admin/customers', []);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.document.0', 'O campo CNPJ/CPF é obrigatório.');
        $response->assertJsonPath('errors.name.0', 'O campo nome é obrigatório.');
        $response->assertJsonPath('errors.email.0', 'O campo e-mail é obrigatório.');
        $response->assertJsonPath('errors.segment.0', 'O campo segmento é obrigatório.');
    }

    public function test_a_duplicate_document_reports_in_portuguese(): void
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
        $response->assertJsonPath('errors.document.0', 'Este CNPJ/CPF já está em uso.');
    }

    public function test_an_invalid_document_reports_in_portuguese(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/admin/customers', [
            'name' => 'Cliente',
            'document' => '11.222.333/0001-80',
            'email' => 'cliente@example.com',
            'segment' => CustomerSegment::Other->value,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath(
            'errors.document.0',
            'O campo CNPJ/CPF deve ser um CPF ou CNPJ válido.',
        );
    }

    public function test_a_task_reports_its_field_names_in_portuguese(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/admin/tasks', []);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.title.0', 'O campo título é obrigatório.');
        $response->assertJsonPath('errors.due_date.0', 'O campo prazo é obrigatório.');
        $response->assertJsonPath('errors.customer_id.0', 'O campo cliente é obrigatório.');
    }

    public function test_a_failed_login_reports_in_portuguese(): void
    {
        User::factory()->create(['email' => 'jane@example.com', 'password' => 'password123']);

        $response = $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'senha-errada',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath(
            'errors.email.0',
            'Estas credenciais não correspondem aos nossos registros.',
        );
    }
}
