<?php

namespace App\Models;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * `status`, `resolved_at` e `closed_at` ficam de fora: só a
     * TransitionTicketStatus escreve neles.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'opened_by',
        'assigned_to',
        'title',
        'category',
        'description',
        'priority',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => TicketCategory::class,
            'priority' => TicketPriority::class,
            'status' => TicketStatus::class,
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $ticket): void {
            $ticket->status ??= TicketStatus::Open;
            $ticket->priority ??= TicketPriority::Medium;
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<TicketComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * @return HasMany<TicketAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): void
    {
        if (array_key_exists('customer_id', $filters) && $filters['customer_id'] !== null && $filters['customer_id'] !== '') {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (array_key_exists('status', $filters) && $filters['status'] !== null && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        } elseif (($filters['active'] ?? null) === '1') {
            $query->whereIn('status', [
                TicketStatus::Open->value,
                TicketStatus::InProgress->value,
                TicketStatus::WaitingCustomer->value,
            ]);
        }

        if (array_key_exists('priority', $filters) && $filters['priority'] !== null && $filters['priority'] !== '') {
            $query->where('priority', $filters['priority']);
        }

        if (array_key_exists('assigned_to', $filters) && $filters['assigned_to'] !== null) {
            if ($filters['assigned_to'] === 'none') {
                $query->whereNull('assigned_to');
            } else {
                $query->where('assigned_to', $filters['assigned_to']);
            }
        }

        $term = trim((string) ($filters['q'] ?? ''));

        if ($term !== '') {
            $number = preg_replace('/^(?:#)?(?:CH-)?/i', '', $term) ?? '';

            $query->where(function (Builder $query) use ($term, $number): void {
                if ($number !== '' && ctype_digit($number)) {
                    $query->where('id', $number)->orWhere('title', 'like', '%'.$term.'%');
                } else {
                    $query->where('title', 'like', '%'.$term.'%');
                }
            });
        }

        $sort = $filters['sort'] ?? 'recent';

        if ($sort === 'updated') {
            $query->orderByDesc('updated_at');
        } elseif ($sort === 'priority') {
            $priorities = TicketPriority::sortOrder();
            $cases = array_map(
                static fn (string $priority, int $order): string => "WHEN '{$priority}' THEN {$order}",
                $priorities,
                array_keys($priorities),
            );

            $query->orderByRaw('CASE priority '.implode(' ', $cases).' ELSE '.count($priorities).' END')
                ->orderBy('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $query->orderByDesc('id');
    }

    public function getReferenceAttribute(): string
    {
        return '#CH-'.$this->id;
    }
}
