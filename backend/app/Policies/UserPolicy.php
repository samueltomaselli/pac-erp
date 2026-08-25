<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class UserPolicy
{
    public function before(?Authenticatable $user, string $ability): ?bool
    {
        return null;
    }

    public function viewAdminArea(?Authenticatable $user): bool
    {
        /** @var User|null $user */
        return $user?->role === UserRole::Admin;
    }
}
