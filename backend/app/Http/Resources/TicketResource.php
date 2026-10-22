<?php

namespace App\Http\Resources;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Relações esperadas: `customer`, `assignee`, `openedBy` e `withCount('comments')`
 * na listagem; no detalhe também `comments.author`, `comments.attachments` e `attachments`.
 *
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isAdmin = $request->user()?->role === UserRole::Admin;

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ]),
            'title' => $this->title,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'description' => $this->description,
            'priority' => $this->priority->value,
            'priority_label' => $this->priority->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'allowed_transitions' => $this->when($isAdmin, fn () => array_map(fn (TicketStatus $status) => [
                'status' => $status->value,
                'label' => $status->label(),
            ], $this->status->allowedTransitions())),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ] : null),
            'opened_by' => $this->whenLoaded('openedBy', fn () => self::person($this->openedBy)),
            'comments_count' => $this->whenCounted('comments'),
            'comments' => $this->whenLoaded('comments', fn () => $this->comments->map(fn (TicketComment $comment) => [
                'id' => $comment->id,
                'body' => $comment->body,
                'author' => self::person($comment->author),
                'attachments' => $comment->relationLoaded('attachments')
                    ? $comment->attachments->map(fn (TicketAttachment $attachment) => self::attachment($attachment))->all()
                    : [],
                'created_at' => $comment->created_at?->toIso8601String(),
            ])->all()),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments
                ->map(fn (TicketAttachment $attachment) => self::attachment($attachment))
                ->all()),
            'opened_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, name: string, role: string}|null
     */
    private static function person(?User $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role->value,
        ] : null;
    }

    /**
     * Sem `path` e sem `disk`: o arquivo só sai pela rota de download.
     *
     * @return array<string, mixed>
     */
    private static function attachment(TicketAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'comment_id' => $attachment->comment_id,
            'created_at' => $attachment->created_at?->toIso8601String(),
        ];
    }
}
