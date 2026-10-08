<?php

namespace App\Actions\Proposals;

use App\Actions\Action;
use App\Actions\Support\ActionResult;
use App\Enums\ProposalStatus;
use App\Enums\PublicProposalStatus;
use App\Models\Proposal;
use App\Models\ProposalAcceptance;
use App\Rules\CpfOrCnpj;
use Illuminate\Support\Facades\DB;

class AcceptProposalPublicly extends Action
{
    public function __construct(private readonly TransitionProposalStatus $transition) {}

    /**
     * @param  array{name: string, document: string, email: string}  $data
     */
    public function handle(Proposal $proposal, array $data, string $ip, ?string $userAgent): ActionResult
    {
        if (PublicProposalStatus::for($proposal) !== PublicProposalStatus::Pending) {
            return $this->unavailable();
        }

        return DB::transaction(function () use ($proposal, $data, $ip, $userAgent): ActionResult {
            // Relê com lock: dois aceites simultâneos não passam os dois pela checagem acima.
            $proposal = Proposal::query()->whereKey($proposal->getKey())->lockForUpdate()->firstOrFail();

            if (PublicProposalStatus::for($proposal) !== PublicProposalStatus::Pending) {
                return $this->unavailable();
            }

            $result = $this->transition->handle($proposal, ProposalStatus::Accepted);

            if (! $result->successful()) {
                return $result;
            }

            ProposalAcceptance::create([
                'proposal_id' => $proposal->id,
                'name' => $data['name'],
                'document' => CpfOrCnpj::digits($data['document']),
                'email' => $data['email'],
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'accepted_at' => now(),
            ]);

            return ActionResult::ok($result->data->load('acceptance'));
        });
    }

    private function unavailable(): ActionResult
    {
        return ActionResult::fail('Esta proposta não está disponível para aceite.');
    }
}
