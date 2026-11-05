<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Proposals\CreateProposal;
use App\Actions\Proposals\TransitionProposalStatus;
use App\Actions\Proposals\UpdateProposal;
use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Proposals\IndexProposalRequest;
use App\Http\Requests\Proposals\StoreProposalRequest;
use App\Http\Requests\Proposals\UpdateProposalRequest;
use App\Http\Resources\ProposalResource;
use App\Models\Customer;
use App\Models\Proposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProposalController extends Controller
{
    public function index(IndexProposalRequest $request): AnonymousResourceCollection
    {
        $proposals = Proposal::query()
            ->with(['customer', 'items'])
            ->withCount('items')
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->integer('customer_id')))
            ->status($request->input('status'))
            ->search($request->input('search'))
            ->orderByDesc('issued_on')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return ProposalResource::collection($proposals);
    }

    public function store(StoreProposalRequest $request, CreateProposal $createProposal): JsonResponse
    {
        $customer = Customer::findOrFail($request->integer('customer_id'));
        $result = $createProposal->handle($customer, $request->validated(), $request->user());

        return (new ProposalResource($result->data))->response()->setStatusCode(201);
    }

    public function show(Proposal $proposal): ProposalResource
    {
        $proposal->load(['customer', 'items', 'acceptance']);

        return new ProposalResource($proposal);
    }

    public function signature(Proposal $proposal): StreamedResponse
    {
        $path = $proposal->acceptance?->signature_path;

        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, "assinatura-{$proposal->reference}.png", [
            'Content-Type' => 'image/png',
        ]);
    }

    public function update(UpdateProposalRequest $request, Proposal $proposal, UpdateProposal $updateProposal): JsonResponse|ProposalResource
    {
        $result = $updateProposal->handle($proposal, $request->validated());

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
