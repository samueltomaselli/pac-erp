<?php

namespace App\Http\Controllers;

use App\Actions\Proposals\AcceptProposalPublicly;
use App\Enums\PublicProposalStatus;
use App\Http\Requests\Proposals\AcceptPublicProposalRequest;
use App\Http\Resources\PublicProposalResource;
use App\Models\Proposal;
use Illuminate\Http\JsonResponse;

class PublicProposalController extends Controller
{
    public function show(Proposal $proposal): PublicProposalResource
    {
        // 404, não 403: um 403 confirmaria que o link existe.
        abort_if(PublicProposalStatus::for($proposal) === null, 404);

        $proposal->load(['customer', 'items', 'acceptance']);

        return new PublicProposalResource($proposal);
    }

    /**
     * Rascunho e recusada já saem com 404 no authorize() do request.
     */
    public function accept(AcceptPublicProposalRequest $request, Proposal $proposal, AcceptProposalPublicly $acceptProposal): JsonResponse|PublicProposalResource
    {
        // IP e user agent vêm da requisição, nunca do corpo.
        $result = $acceptProposal->handle($proposal, $request->validated(), $request->ip(), $request->userAgent());

        if (! $result->successful()) {
            return response()->json(['message' => $result->message], 422);
        }

        return new PublicProposalResource($result->data);
    }
}
