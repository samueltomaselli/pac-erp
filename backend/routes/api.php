<?php

use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\ProposalCatalogItemController;
use App\Http\Controllers\Admin\ProposalItemController;
use App\Http\Controllers\Admin\TaskController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Customer\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [RegisteredUserController::class, 'store']);
Route::post('/login', [AuthenticatedSessionController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);

    Route::get('/me', function (Request $request) {
        $user = $request->user();

        return response()->json([
            'user' => $user,
            'role' => $user->role,
        ]);
    });
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/ping', fn () => response()->json(['ok' => true]));

    Route::apiResource('customers', CustomerController::class)->withTrashed(['show']);
    Route::post('customers/{customer}/restore', [CustomerController::class, 'restore'])
        ->withTrashed()
        ->name('customers.restore');

    Route::apiResource('tasks', TaskController::class);
    Route::post('tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');
    Route::post('tasks/{task}/reopen', [TaskController::class, 'reopen'])->name('tasks.reopen');

    Route::apiResource('proposals.items', ProposalItemController::class)
        ->only(['store', 'update', 'destroy'])
        ->scopeBindings();
    Route::apiResource('proposal-catalog-items', ProposalCatalogItemController::class)
        ->parameters(['proposal-catalog-items' => 'catalogItem']);
});

Route::middleware(['auth:sanctum', 'role:customer'])->prefix('customer')->group(function () {
    Route::get('/ping', fn () => response()->json(['ok' => true]));

    Route::get('/profile', [ProfileController::class, 'show'])->name('customer.profile');
});
