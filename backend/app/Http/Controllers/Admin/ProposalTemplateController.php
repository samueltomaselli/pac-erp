<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Proposals\IndexProposalTemplateRequest;
use App\Http\Requests\Proposals\StoreProposalTemplateRequest;
use App\Http\Requests\Proposals\UpdateProposalTemplateRequest;
use App\Http\Resources\ProposalTemplateResource;
use App\Models\ProposalTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProposalTemplateController extends Controller
{
    public function index(IndexProposalTemplateRequest $request): AnonymousResourceCollection
    {
        $templates = ProposalTemplate::query()
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->input('type')))
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return ProposalTemplateResource::collection($templates);
    }

    public function store(StoreProposalTemplateRequest $request): JsonResponse
    {
        $template = ProposalTemplate::create($request->validated());

        return (new ProposalTemplateResource($template))->response()->setStatusCode(201);
    }

    public function show(ProposalTemplate $proposalTemplate): ProposalTemplateResource
    {
        return new ProposalTemplateResource($proposalTemplate);
    }

    public function update(UpdateProposalTemplateRequest $request, ProposalTemplate $proposalTemplate): ProposalTemplateResource
    {
        $proposalTemplate->fill($request->validated());
        $proposalTemplate->save();

        return new ProposalTemplateResource($proposalTemplate);
    }

    public function destroy(ProposalTemplate $proposalTemplate): JsonResponse
    {
        $proposalTemplate->delete();

        return response()->json(['message' => 'Template removido.']);
    }
}
