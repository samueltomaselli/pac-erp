<?php

namespace App\Actions\Auth;

use App\Actions\Action;
use App\Actions\Support\ActionResult;
use App\Enums\UserRole;
use App\Models\User;

class RegisterUser extends Action
{
    public function handle(array $data): ActionResult
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => UserRole::Customer,
        ]);

        return ActionResult::ok($user);
    }
}
