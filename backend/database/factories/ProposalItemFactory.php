<?php

namespace Database\Factories;

use App\Enums\ProposalItemType;
use App\Models\Proposal;
use App\Models\ProposalItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProposalItem>
 */
class ProposalItemFactory extends Factory
{
    protected $model = ProposalItem::class;

    public function definition(): array
    {
        return [
            'proposal_id' => Proposal::factory(),
            'type' => ProposalItemType::OneTime,
            'description' => fake()->sentence(4),
            'quantity' => 1,
            'unit_amount_cents' => 50000,
            'discount_cents' => 0,
            'installments' => 1,
        ];
    }

    public function recurring(): static
    {
        return $this->state(['type' => ProposalItemType::Recurring, 'installments' => 1]);
    }

    public function oneTime(): static
    {
        return $this->state(['type' => ProposalItemType::OneTime]);
    }

    public function installments(int $n): static
    {
        return $this->state(['type' => ProposalItemType::OneTime, 'installments' => $n]);
    }
}
