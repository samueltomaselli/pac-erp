<?php

namespace Tests\Feature\Proposals;

use App\Enums\ProposalTemplateType;
use App\Models\ProposalTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProposalTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_crud_templates(): void
    {
        $admin = User::factory()->admin()->create();

        $createResponse = $this->actingAs($admin)->postJson('/api/admin/proposal-templates', [
            'name' => 'Modelo de observações',
            'type' => ProposalTemplateType::Observations->value,
            'content' => 'Prazo de implantação de 15 dias.',
        ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('data.name', 'Modelo de observações')
            ->assertJsonPath('data.type', ProposalTemplateType::Observations->value);

        $templateId = $createResponse->json('data.id');

        $this->actingAs($admin)->getJson('/api/admin/proposal-templates')
            ->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Modelo de observações');

        $this->actingAs($admin)->putJson('/api/admin/proposal-templates/'.$templateId, [
            'name' => 'Novo nome',
            'type' => ProposalTemplateType::GeneralConditions->value,
            'content' => 'Cláusula alterada.',
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'Novo nome');

        $this->actingAs($admin)->deleteJson('/api/admin/proposal-templates/'.$templateId)
            ->assertStatus(200)
            ->assertJsonFragment(['message' => 'Template removido.']);
    }

    public function test_templates_can_be_filtered_by_type(): void
    {
        $admin = User::factory()->admin()->create();

        ProposalTemplate::factory()->create([
            'type' => ProposalTemplateType::Observations,
            'name' => 'B',
        ]);
        ProposalTemplate::factory()->create([
            'type' => ProposalTemplateType::GeneralConditions,
            'name' => 'A',
        ]);

        $this->actingAs($admin)->getJson('/api/admin/proposal-templates?type='.ProposalTemplateType::Observations->value)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', ProposalTemplateType::Observations->value);
    }

    public function test_invalid_template_type_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/admin/proposal-templates', [
            'name' => 'Teste',
            'type' => 'invalid-type',
            'content' => 'Conteúdo',
        ])->assertStatus(422);
    }

    public function test_customers_cannot_access_templates(): void
    {
        $customer = User::factory()->state(['role' => 'customer'])->create();

        $this->actingAs($customer)->getJson('/api/admin/proposal-templates')
            ->assertStatus(403);

        $this->actingAs($customer)->postJson('/api/admin/proposal-templates', [
            'name' => 'Teste',
            'type' => ProposalTemplateType::Observations->value,
            'content' => 'Conteúdo',
        ])->assertStatus(403);
    }
}
