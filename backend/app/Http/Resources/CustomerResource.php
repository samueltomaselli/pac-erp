<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'document' => $this->document,
            'document_formatted' => $this->document_formatted,
            'email' => $this->email,
            'phone' => $this->phone,
            'contact_name' => $this->contact_name,
            'segment' => $this->segment->value,
            'segment_label' => $this->segment->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'email' => $this->user->email,
                'role' => $this->user->role->value,
            ]),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            'tasks_count' => $this->whenCounted('tasks'),
            'pending_tasks_count' => $this->whenCounted('pendingTasks'),
        ];
    }
}
