<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Proposals\AddProposalItem;
use App\Actions\Proposals\RemoveProposalItem;
use App\Actions\Proposals\UpdateProposalItem;
use App\Actions\Support\ActionResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Proposals\StoreProposalItemRequest;
use App\Http\Requests\Proposals\UpdateProposalItemRequest;
use App\Http\Resources\ProposalResource;
use App\Models\Proposal;
use App\Models\ProposalItem;
use Illuminate\Http\JsonResponse;

class ProposalItemController extends Controller
{
    public function store(StoreProposalItemRequest $request, Proposal $proposal, AddProposalItem $addProposalItem): JsonResponse
    {
        return $this->respond($addProposalItem->handle($proposal, $request->validated()), 201);
    }

    public function update(UpdateProposalItemRequest $request, Proposal $proposal, ProposalItem $item, UpdateProposalItem $updateProposalItem): JsonResponse
    {
        abort_unless($item->proposal_id === $proposal->id, 404);

        return $this->respond($updateProposalItem->handle($proposal, $item, $request->validated()));
    }

    public function destroy(Proposal $proposal, ProposalItem $item, RemoveProposalItem $removeProposalItem): JsonResponse
    {
        abort_unless($item->proposal_id === $proposal->id, 404);

        return $this->respond($removeProposalItem->handle($proposal, $item));
    }

    private function respond(ActionResult $result, int $status = 200): JsonResponse
    {
        if (! $result->successful()) {
            return response()->json(['message' => $result->message], 422);
        }

        return (new ProposalResource($result->data))->response()->setStatusCode($status);
    }
}
