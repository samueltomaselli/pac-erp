<?php

namespace Tests\Feature\Proposals;

use App\Models\Proposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProposalSignatureTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Maria Cliente',
            'document' => '529.982.247-25',
            'email' => 'maria@example.com',
            'signature' => self::PNG,
            'agreed' => true,
        ], $overrides);
    }

    private function accept(Proposal $proposal, array $overrides = [])
    {
        return $this->postJson("/api/public/proposals/{$proposal->pub_id}/accept", $this->payload($overrides));
    }

    public function test_acceptance_stores_the_signature_on_the_private_disk(): void
    {
        $proposal = Proposal::factory()->sent()->hasItems(1)->create();

        $this->accept($proposal)->assertOk();

        $path = $proposal->acceptance()->first()->signature_path;
        $this->assertStringStartsWith("proposals/{$proposal->id}/", $path);
        Storage::disk('local')->assertExists($path);
        $this->assertStringStartsWith("\x89PNG", Storage::disk('local')->get($path));
    }

    public function test_acceptance_without_signature_is_refused(): void
    {
        $proposal = Proposal::factory()->sent()->hasItems(1)->create();

        $this->accept($proposal, ['signature' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['signature' => 'Assine no quadro antes de aceitar a proposta.']);

        $this->assertSame('sent', $proposal->fresh()->status->value);
        $this->assertNull($proposal->acceptance()->first());
    }

    public function test_signature_that_is_not_a_png_is_refused(): void
    {
        $proposal = Proposal::factory()->sent()->hasItems(1)->create();

        foreach (['texto qualquer', 'data:image/png;base64,'.base64_encode('nao e imagem'), 'data:image/jpeg;base64,'.base64_encode("\x89PNG\r\n\x1a\n")] as $signature) {
            $this->accept($proposal, ['signature' => $signature])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['signature' => 'A assinatura enviada é inválida. Assine novamente.']);
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_signature_above_the_size_limit_is_refused(): void
    {
        $proposal = Proposal::factory()->sent()->hasItems(1)->create();
        $big = 'data:image/png;base64,'.base64_encode("\x89PNG\r\n\x1a\n".str_repeat('a', 600 * 1024));

        $this->accept($proposal, ['signature' => $big])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['signature' => 'A assinatura enviada é grande demais. Assine novamente.']);
    }

    public function test_acceptance_without_agreeing_to_the_electronic_signature_is_refused(): void
    {
        $proposal = Proposal::factory()->sent()->hasItems(1)->create();

        $this->accept($proposal, ['agreed' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agreed' => 'Confirme que concorda com a assinatura eletrônica.']);

        $this->assertSame('sent', $proposal->fresh()->status->value);
    }

    public function test_admin_sees_the_acceptance_and_downloads_the_signature(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->sent()->hasItems(1)->create();
        $this->accept($proposal)->assertOk();

        $this->actingAs($admin)->getJson("/api/admin/proposals/{$proposal->id}")
            ->assertOk()
            ->assertJsonPath('data.acceptance.name', 'Maria Cliente')
            ->assertJsonPath('data.acceptance.document', '52998224725')
            ->assertJsonPath('data.acceptance.has_signature', true)
            ->assertJsonMissingPath('data.acceptance.signature_path');

        $response = $this->actingAs($admin)->get("/api/admin/proposals/{$proposal->id}/signature");
        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
    }

    public function test_signature_route_returns_404_without_acceptance(): void
    {
        $admin = User::factory()->admin()->create();
        $proposal = Proposal::factory()->sent()->hasItems(1)->create();

        $this->actingAs($admin)->get("/api/admin/proposals/{$proposal->id}/signature")->assertNotFound();
        $this->actingAs($admin)->getJson("/api/admin/proposals/{$proposal->id}")->assertJsonPath('data.acceptance', null);
    }

    public function test_customer_cannot_download_a_signature(): void
    {
        $proposal = Proposal::factory()->sent()->hasItems(1)->create();
        $this->accept($proposal)->assertOk();
        $customer = User::factory()->create();

        $this->actingAs($customer)->get("/api/admin/proposals/{$proposal->id}/signature")->assertForbidden();
    }

    public function test_public_payload_does_not_expose_the_signature(): void
    {
        $proposal = Proposal::factory()->sent()->hasItems(1)->create();
        $this->accept($proposal)->assertOk();

        $this->getJson("/api/public/proposals/{$proposal->pub_id}")
            ->assertOk()
            ->assertJsonMissingPath('data.acceptance.signature_path')
            ->assertJsonMissingPath('data.acceptance.document');
    }
}
