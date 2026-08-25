<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RegisteredUserController extends Controller
{
    public function store(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        $result = $registerUser->handle($request->validated());

        /** @var User $user */
        $user = $result->data;

        Auth::login($user);

        $request->session()->regenerate();

        return response()->json([
            'user' => $user,
            'role' => $user->role,
        ], 201);
    }
}
