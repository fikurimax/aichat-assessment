<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PurchaseTransaction>
 */
class PurchaseTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $random_float = function () {
            $dec = pow(10, 2);
            return mt_rand(0 * $dec, 10 * $dec) / $dec;
        };

        return [
            'total_spent' => $random_float(),
            'total_saving' => $random_float()
        ];
    }
}
