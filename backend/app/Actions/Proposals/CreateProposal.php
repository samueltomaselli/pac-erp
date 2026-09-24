<?php

namespace App\Actions\Proposals;

use App\Actions\Action;
use App\Actions\Support\ActionResult;
use App\Enums\ProposalStatus;
use App\Models\Customer;
use App\Models\User;

class CreateProposal extends Action
{
    public function handle(Customer $customer, array $data, ?User $creator = null): ActionResult
    {
        $proposal = $customer->proposals()->create([
            'created_by' => $creator?->id,
            'title' => $data['title'],
            'issued_on' => $data['issued_on'],
            'valid_until' => $data['valid_until'],
            'payment_method' => $data['payment_method'] ?? null,
            'payment_notes' => $data['payment_notes'] ?? null,
            'observations' => $data['observations'] ?? null,
            'terms' => $data['terms'] ?? null,
            'status' => ProposalStatus::Draft,
        ]);

        return ActionResult::ok($proposal->load('customer', 'items'));
    }
}
