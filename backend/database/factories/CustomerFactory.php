<?php

namespace Database\Factories;

use App\Enums\CustomerSegment;
use App\Enums\CustomerStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $email = fake()->unique()->companyEmail();

        return [
            'user_id' => User::factory()->state([
                'email' => $email,
                'role' => UserRole::Customer,
            ]),
            'name' => fake()->company(),
            'document' => self::cnpj(),
            'email' => $email,
            'phone' => fake()->numerify('(##) #####-####'),
            'contact_name' => fake()->name(),
            'segment' => fake()->randomElement(CustomerSegment::cases()),
            'status' => CustomerStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CustomerStatus::Inactive,
        ]);
    }

    public function segment(CustomerSegment $segment): static
    {
        return $this->state(fn (array $attributes) => [
            'segment' => $segment,
        ]);
    }

    public static function cnpj(): string
    {
        $digits = '';

        for ($i = 0; $i < 12; $i++) {
            $digits .= random_int(0, 9);
        }

        foreach ([[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]] as $weights) {
            $sum = 0;

            foreach ($weights as $i => $weight) {
                $sum += (int) $digits[$i] * $weight;
            }

            $remainder = $sum % 11;
            $digits .= $remainder < 2 ? 0 : 11 - $remainder;
        }

        return $digits;
    }
}
