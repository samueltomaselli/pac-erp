<?php

namespace App\Actions\Customers;

use App\Actions\Action;
use App\Actions\Support\ActionResult;
use App\Enums\CustomerStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCustomer extends Action
{
    public function handle(array $data): ActionResult
    {
        $password = Str::password(12);

        $customer = DB::transaction(function () use ($data, $password): Customer {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $password,
                'role' => UserRole::Customer,
            ]);

            return Customer::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'document' => $data['document'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'contact_name' => $data['contact_name'] ?? null,
                'segment' => $data['segment'],
                'status' => $data['status'] ?? CustomerStatus::Active->value,
            ]);
        });

        return ActionResult::ok([
            'customer' => $customer->load('user'),
            'generated_password' => $password,
        ]);
    }
}
