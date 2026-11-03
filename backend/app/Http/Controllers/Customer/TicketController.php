<?php

namespace App\Http\Controllers\Customer;

use App\Actions\Tickets\OpenTicket;
use App\Http\Controllers\Concerns\LoadsTicketDetail;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\IndexCustomerTicketRequest;
use App\Http\Requests\Tickets\StoreCustomerTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Tudo aqui parte de `$request->user()->customer`. Nenhuma consulta sai do
 * cadastro do cliente logado, e o id da URL só é procurado dentro dele.
 */
class TicketController extends Controller
{
    use LoadsTicketDetail;

    public function index(IndexCustomerTicketRequest $request): JsonResponse|AnonymousResourceCollection
    {
        $customer = self::customerOf($request);

        if (! $customer) {
            return self::noCustomer();
        }

        $tickets = $customer->tickets()
            ->with(['customer', 'assignee', 'openedBy'])
            ->withCount('comments')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return TicketResource::collection($tickets);
    }

    public function store(StoreCustomerTicketRequest $request, OpenTicket $openTicket): JsonResponse
    {
        $customer = self::customerOf($request);

        if (! $customer) {
            return self::noCustomer();
        }

        $result = $openTicket->handle($customer, $request->validated(), $request->user());

        if (! $result->successful()) {
            return response()->json(['message' => $result->message], 422);
        }

        return (new TicketResource($this->loadTicketDetail($result->data)))->response()->setStatusCode(201);
    }

    public function show(Request $request, string $ticket): JsonResponse|TicketResource
    {
        $customer = self::customerOf($request);

        if (! $customer) {
            return self::noCustomer();
        }

        $found = $customer->tickets()
            ->whereKey($ticket)
            ->first();

        if (! $found) {
            return response()->json(['message' => 'Chamado não encontrado.'], 404);
        }

        return new TicketResource($this->loadTicketDetail($found));
    }

    /**
     * Inclui o cadastro inativado: os chamados antigos continuam visíveis,
     * e a OpenTicket é quem recusa abrir chamado novo.
     */
    private static function customerOf(Request $request): ?Customer
    {
        return $request->user()->customer()->withTrashed()->first();
    }

    private static function noCustomer(): JsonResponse
    {
        return response()->json(['message' => 'Nenhum cliente vinculado a este acesso.'], 404);
    }
}
