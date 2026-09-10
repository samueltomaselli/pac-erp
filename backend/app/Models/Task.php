<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'created_by',
        'title',
        'description',
        'due_date',
        'priority',
        'status',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'priority' => TaskPriority::class,
            'status' => TaskStatus::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function isOverdue(): Attribute
    {
        return Attribute::get(fn (): bool => $this->status === TaskStatus::Pending
            && $this->due_date !== null
            && $this->due_date->startOfDay()->lt(today()));
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function scopeStatus(Builder $query, TaskStatus|string|null $status): void
    {
        if ($status === null || $status === '') {
            return;
        }

        $query->where('status', $status instanceof TaskStatus ? $status->value : $status);
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->where('status', TaskStatus::Pending)->whereDate('due_date', '<', today());
    }
}
