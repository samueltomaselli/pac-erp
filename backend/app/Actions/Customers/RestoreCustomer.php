<?php

namespace App\Actions\Customers;

use App\Actions\Action;
use App\Actions\Support\ActionResult;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class RestoreCustomer extends Action
{
    public function handle(Customer $customer): ActionResult
    {
        DB::transaction(function () use ($customer): void {
            $customer->restore();
            $customer->status = CustomerStatus::Active;
            $customer->save();
        });

        return ActionResult::ok($customer->refresh());
    }
}
