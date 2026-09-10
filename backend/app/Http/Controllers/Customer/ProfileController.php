<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse|CustomerResource
    {
        $customer = $request->user()->customer()
            ->with(['tasks' => fn ($query) => $query->orderBy('due_date')])
            ->first();

        if (! $customer) {
            return response()->json(['message' => 'Nenhum cliente vinculado a este acesso.'], 404);
        }

        return new CustomerResource($customer);
    }
}
