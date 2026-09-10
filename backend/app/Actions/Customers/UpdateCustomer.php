<?php

namespace App\Actions\Customers;

use App\Actions\Action;
use App\Actions\Support\ActionResult;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class UpdateCustomer extends Action
{
    public function handle(Customer $customer, array $data): ActionResult
    {
        DB::transaction(function () use ($customer, $data): void {
            $customer->fill($data)->save();

            $customer->user?->fill([
                'name' => $customer->name,
                'email' => $customer->email,
            ])->save();
        });

        return ActionResult::ok($customer->refresh()->load('user'));
    }
}
