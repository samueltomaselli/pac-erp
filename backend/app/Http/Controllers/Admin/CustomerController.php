<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Customers\CreateCustomer;
use App\Actions\Customers\DeactivateCustomer;
use App\Actions\Customers\RestoreCustomer;
use App\Actions\Customers\UpdateCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\IndexCustomerRequest;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    public function index(IndexCustomerRequest $request): AnonymousResourceCollection
    {
        $customers = Customer::query()
            ->search($request->input('search'))
            ->status($request->input('status'))
            ->when($request->filled('segment'), fn ($query) => $query->where('segment', $request->input('segment')))
            ->when($request->input('trashed') === 'with', fn ($query) => $query->withTrashed())
            ->when($request->input('trashed') === 'only', fn ($query) => $query->onlyTrashed())
            ->withCount(['tasks', 'pendingTasks'])
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return CustomerResource::collection($customers);
    }

    public function store(StoreCustomerRequest $request, CreateCustomer $createCustomer): JsonResponse
    {
        $result = $createCustomer->handle($request->validated());

        return response()->json([
            'data' => new CustomerResource($result->data['customer']),
            'generated_password' => $result->data['generated_password'],
        ], 201);
    }

    public function show(Customer $customer): CustomerResource
    {
        $customer->load([
            'user',
            'tasks' => fn ($query) => $query->orderBy('status')->orderBy('due_date'),
        ])->loadCount(['tasks', 'pendingTasks']);

        return new CustomerResource($customer);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer, UpdateCustomer $updateCustomer): CustomerResource
    {
        $result = $updateCustomer->handle($customer, $request->validated());

        return new CustomerResource($result->data);
    }

    public function destroy(Customer $customer, DeactivateCustomer $deactivateCustomer): JsonResponse
    {
        $deactivateCustomer->handle($customer);

        return response()->json(['message' => 'Cliente inativado.']);
    }

    public function restore(Customer $customer, RestoreCustomer $restoreCustomer): CustomerResource
    {
        $result = $restoreCustomer->handle($customer);

        return new CustomerResource($result->data);
    }
}
