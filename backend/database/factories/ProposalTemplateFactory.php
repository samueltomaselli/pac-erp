<?php

namespace Database\Factories;

use App\Enums\ProposalTemplateType;
use App\Models\ProposalTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProposalTemplate>
 */
class ProposalTemplateFactory extends Factory
{
    protected $model = ProposalTemplate::class;

    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'type' => fake()->randomElement(ProposalTemplateType::cases()),
            'content' => fake()->paragraph(),
        ];
    }
}
