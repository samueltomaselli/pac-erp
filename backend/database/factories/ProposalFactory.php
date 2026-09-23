<?php

namespace Database\Factories;

use App\Enums\ProposalStatus;
use App\Models\Customer;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proposal>
 */
class ProposalFactory extends Factory
{
    protected $model = Proposal::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'created_by' => User::factory()->admin(),
            'title' => fake()->sentence(4),
            'issued_on' => now()->toDateString(),
            'valid_until' => now()->addMonth()->toDateString(),
            'payment_method' => null,
            'payment_notes' => null,
            'observations' => fake()->paragraph(),
            'terms' => fake()->paragraph(),
            'status' => ProposalStatus::Draft,
            'sent_at' => null,
            'decided_at' => null,
            'pub_id' => fake()->uuid(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => ProposalStatus::Draft]);
    }

    public function sent(): static
    {
        return $this->state(fn () => ['status' => ProposalStatus::Sent, 'sent_at' => now()]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['status' => ProposalStatus::Accepted, 'decided_at' => now()]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => ProposalStatus::Rejected, 'decided_at' => now()]);
    }

    public function hasItems(int $count): static
    {
        return $this->afterCreating(function (Proposal $proposal) use ($count): void {
            ProposalItem::factory()->count($count)->for($proposal)->create();
        });
    }
}
