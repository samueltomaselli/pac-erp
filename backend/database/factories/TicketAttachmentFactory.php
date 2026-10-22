<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TicketAttachment>
 */
class TicketAttachmentFactory extends Factory
{
    protected $model = TicketAttachment::class;

    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'comment_id' => null,
            'uploaded_by' => User::factory()->admin(),
            'disk' => 'local',
            'path' => fn (array $attributes) => 'tickets/'.$attributes['ticket_id'].'/'.Str::uuid().'.pdf',
            'original_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1_000, 500_000),
        ];
    }

    public function forComment(TicketComment $comment): static
    {
        return $this->state(fn () => [
            'ticket_id' => $comment->ticket_id,
            'comment_id' => $comment->id,
        ]);
    }
}
