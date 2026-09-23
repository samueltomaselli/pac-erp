<?php

namespace Database\Factories;

use App\Enums\ProposalItemType;
use App\Models\ProposalCatalogItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProposalCatalogItem>
 */
class ProposalCatalogItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'type' => ProposalItemType::Recurring,
            'default_quantity' => 1,
            'default_unit_amount_cents' => fake()->numberBetween(100, 5000) * 100,
            'allows_installments' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function oneTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProposalItemType::OneTime,
        ]);
    }

    public function allowsInstallments(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProposalItemType::OneTime,
            'allows_installments' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
