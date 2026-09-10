<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Customer;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'created_by' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'due_date' => fake()->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d'),
            'priority' => fake()->randomElement(TaskPriority::cases()),
            'status' => TaskStatus::Pending,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::Pending,
            'due_date' => today()->subDays(3)->format('Y-m-d'),
            'completed_at' => null,
        ]);
    }

    public function dueOn(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => $date,
        ]);
    }

    public function priority(TaskPriority $priority): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $priority,
        ]);
    }
}
