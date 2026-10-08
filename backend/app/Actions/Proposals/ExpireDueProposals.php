<?php

namespace App\Actions\Proposals;

use App\Actions\Action;
use App\Actions\Support\ActionResult;
use App\Enums\ProposalStatus;
use App\Models\Proposal;
use Illuminate\Support\Collection;
use Throwable;

class ExpireDueProposals extends Action
{
    public function __construct(private readonly TransitionProposalStatus $transition) {}

    public function handle(): ActionResult
    {
        $expired = 0;

        Proposal::query()
            ->status(ProposalStatus::Sent)
            ->whereDate('valid_until', '<', today())
            ->chunkById(100, function (Collection $proposals) use (&$expired): void {
                foreach ($proposals as $proposal) {
                    if ($this->expire($proposal)) {
                        $expired++;
                    }
                }
            });

        return ActionResult::ok(['expired' => $expired]);
    }

    private function expire(Proposal $proposal): bool
    {
        try {
            // O chunk pode estar velho: se a proposta foi aceita ou recusada depois da
            // consulta, a transição precisa enxergar o status atual para recusar o vencimento.
            $proposal->refresh();

            return $this->transition->handle($proposal, ProposalStatus::Expired)->successful();
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
