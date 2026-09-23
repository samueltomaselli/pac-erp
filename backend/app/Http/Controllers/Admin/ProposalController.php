<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Proposals\TransitionProposalStatus;
use App\Actions\Proposals\UpdateProposal;
use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProposalResource;
use App\Models\Proposal;
use Illuminate\Http\JsonResponse;

class ProposalController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => []]);
    }

    public function store(): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function show(Proposal $proposal): ProposalResource
    {
        $proposal->load(['customer', 'items']);

        return new ProposalResource($proposal);
    }

    public function update(Proposal $proposal, UpdateProposal $updateProposal): JsonResponse|ProposalResource
    {
        $result = $updateProposal->handle($proposal, request()->all());

        if (! $result->successful()) {
            return response()->json(['message' => $result->message], 422);
        }

        return new ProposalResource($result->data);
    }

    public function destroy(Proposal $proposal, UpdateProposal $updateProposal): JsonResponse
    {
        $result = $updateProposal->handleDelete($proposal);

        if (! $result->successful()) {
            return response()->json(['message' => $result->message], 422);
        }

        return response()->json(['message' => 'Proposta removida.']);
    }

    public function send(Proposal $proposal, TransitionProposalStatus $transition): JsonResponse
    {
        $result = $transition->handle($proposal, ProposalStatus::Sent);

        if (! $result->successful()) {
            return response()->json(['message' => $result->message], 422);
        }

        return (new ProposalResource($result->data))->response();
    }

    public function accept(Proposal $proposal, TransitionProposalStatus $transition): JsonResponse
    {
        $result = $transition->handle($proposal, ProposalStatus::Accepted);

        if (! $result->successful()) {
            return response()->json(['message' => $result->message], 422);
        }

        return (new ProposalResource($result->data))->response();
    }

    public function reject(Proposal $proposal, TransitionProposalStatus $transition): JsonResponse
    {
        $result = $transition->handle($proposal, ProposalStatus::Rejected);

        if (! $result->successful()) {
            return response()->json(['message' => $result->message], 422);
        }

        return (new ProposalResource($result->data))->response();
    }
}
