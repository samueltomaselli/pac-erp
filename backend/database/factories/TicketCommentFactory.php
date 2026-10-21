<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketComment>
 */
class TicketCommentFactory extends Factory
{
    protected $model = TicketComment::class;

    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'user_id' => User::factory()->admin(),
            'body' => fake()->paragraph(),
        ];
    }

    /**
     * Comentário escrito pelo usuário do cliente dono do chamado.
     */
    public function byCustomer(): static
    {
        return $this->state(fn () => [
            'user_id' => fn (array $attributes) => Ticket::find($attributes['ticket_id'])->customer->user_id,
        ]);
    }

    public function by(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }
}
