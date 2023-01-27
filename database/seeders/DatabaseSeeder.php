<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\Customer;
use App\Models\PurchaseTransaction;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\Voucher::factory(100)->create();

        \App\Models\User::factory(150)->create(function () {
            $customer = Customer::factory()->create();
            return [
                'name' => $customer->fullname,
                'email' => $customer->email,
                'customer_id' => $customer->id
            ];
        })->each(function (\App\Models\User $user) {
            PurchaseTransaction::factory(random_int(1, 5))->create([
                'customer_id' => $user->customer_id,
                'created_at' => Carbon::now()->subDays(random_int(25, 90))
            ]);
        });
    }
}
