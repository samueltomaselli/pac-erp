<?php

namespace App\Actions\Proposals;

use App\Actions\Action;
use App\Actions\Support\ActionResult;
use App\Models\Proposal;

class UpdateProposal extends Action
{
    public function handle(Proposal $proposal, array $data): ActionResult
    {
        if (! $proposal->status->isEditable()) {
            return ActionResult::fail('Somente propostas em rascunho podem ser editadas.');
        }

        unset($data['status']);

        $proposal->fill($data);
        $proposal->save();

        return ActionResult::ok($proposal->fresh()->load(['customer', 'items']));
    }

    public function handleDelete(Proposal $proposal): ActionResult
    {
        if (! $proposal->status->isEditable()) {
            return ActionResult::fail('Somente propostas em rascunho podem ser removidas.');
        }

        $proposal->delete();

        return ActionResult::ok();
    }
}
