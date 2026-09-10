<?php

namespace App\Actions\Customers;

use App\Actions\Action;
use App\Actions\Support\ActionResult;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class DeactivateCustomer extends Action
{
    public function handle(Customer $customer): ActionResult
    {
        DB::transaction(function () use ($customer): void {
            $customer->status = CustomerStatus::Inactive;
            $customer->save();
            $customer->delete();
        });

        return ActionResult::ok($customer);
    }
}
