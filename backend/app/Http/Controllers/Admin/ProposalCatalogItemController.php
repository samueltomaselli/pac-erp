<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Proposals\IndexProposalCatalogItemRequest;
use App\Http\Requests\Proposals\StoreProposalCatalogItemRequest;
use App\Http\Requests\Proposals\UpdateProposalCatalogItemRequest;
use App\Http\Resources\CatalogItemResource;
use App\Models\ProposalCatalogItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProposalCatalogItemController extends Controller
{
    public function index(IndexProposalCatalogItemRequest $request): AnonymousResourceCollection
    {
        $catalogItems = ProposalCatalogItem::query()
            ->when($request->filled('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->ordered()
            ->get();

        return CatalogItemResource::collection($catalogItems);
    }

    public function store(StoreProposalCatalogItemRequest $request): JsonResponse
    {
        $catalogItem = ProposalCatalogItem::create($request->validated());

        return (new CatalogItemResource($catalogItem))->response()->setStatusCode(201);
    }

    public function show(ProposalCatalogItem $catalogItem): CatalogItemResource
    {
        return new CatalogItemResource($catalogItem);
    }

    public function update(UpdateProposalCatalogItemRequest $request, ProposalCatalogItem $catalogItem): CatalogItemResource
    {
        $catalogItem->fill($request->validated())->save();

        return new CatalogItemResource($catalogItem->refresh());
    }

    public function destroy(ProposalCatalogItem $catalogItem): JsonResponse
    {
        $catalogItem->delete();

        return response()->json(['message' => 'Item do catálogo removido.']);
    }
}
