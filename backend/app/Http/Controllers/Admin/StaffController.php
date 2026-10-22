<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Admins disponíveis no seletor de responsável do chamado.
 */
class StaffController extends Controller
{
    public function index(): JsonResponse
    {
        $staff = User::query()
            ->where('role', UserRole::Admin)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['data' => $staff]);
    }
}
