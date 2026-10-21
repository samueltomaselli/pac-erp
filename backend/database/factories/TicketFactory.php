<?php

namespace Database\Factories;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'opened_by' => User::factory()->admin(),
            'assigned_to' => null,
            'title' => fake()->sentence(4),
            'category' => fake()->randomElement(TicketCategory::cases()),
            'description' => fake()->paragraph(),
            'priority' => TicketPriority::Medium,
            'status' => TicketStatus::Open,
            'resolved_at' => null,
            'closed_at' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => ['status' => TicketStatus::Open]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => ['status' => TicketStatus::InProgress]);
    }

    public function waitingCustomer(): static
    {
        return $this->state(fn () => ['status' => TicketStatus::WaitingCustomer]);
    }

    public function resolved(): static
    {
        return $this->state(fn () => ['status' => TicketStatus::Resolved, 'resolved_at' => now()]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Closed,
            'resolved_at' => now(),
            'closed_at' => now(),
        ]);
    }

    public function status(TicketStatus $status): static
    {
        return match ($status) {
            TicketStatus::Open => $this->open(),
            TicketStatus::InProgress => $this->inProgress(),
            TicketStatus::WaitingCustomer => $this->waitingCustomer(),
            TicketStatus::Resolved => $this->resolved(),
            TicketStatus::Closed => $this->closed(),
        };
    }

    public function priority(TicketPriority $priority): static
    {
        return $this->state(fn () => ['priority' => $priority]);
    }

    public function assignedTo(User $admin): static
    {
        return $this->state(fn () => ['assigned_to' => $admin->id]);
    }

    /**
     * Chamado aberto pelo próprio cliente (o usuário vinculado ao cadastro).
     */
    public function openedByCustomer(): static
    {
        return $this->state(fn () => [
            'opened_by' => fn (array $attributes) => Customer::withTrashed()->find($attributes['customer_id'])?->user_id,
        ]);
    }
}
