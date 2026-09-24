<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\ProposalStatus;
use Database\Factories\ProposalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Proposal extends Model
{
    /** @use HasFactory<ProposalFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'created_by',
        'title',
        'issued_on',
        'valid_until',
        'payment_method',
        'payment_notes',
        'observations',
        'terms',
        'sent_at',
        'decided_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'decided_at' => 'datetime',
            'payment_method' => PaymentMethod::class,
            'status' => ProposalStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $proposal): void {
            $proposal->pub_id ??= (string) Str::uuid();
            $proposal->status ??= ProposalStatus::Draft;
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ProposalItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProposalItem::class);
    }

    public function getReferenceAttribute(): string
    {
        return '#PC-'.$this->id;
    }

    public function getIsEditableAttribute(): bool
    {
        return $this->status->isEditable();
    }

    /** @param Builder<Proposal> $query */
    public function scopeStatus(Builder $query, ProposalStatus|string|null $status): void
    {
        if ($status === null || $status === '') {
            return;
        }

        $query->where('status', $status instanceof ProposalStatus ? $status->value : $status);
    }

    /** @param Builder<Proposal> $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        $reference = preg_replace('/^#?pc-/i', '', $term);
        $reference = preg_replace('/\D/', '', (string) $reference);

        $query->where(function (Builder $query) use ($term, $reference): void {
            $query->where('title', 'like', '%'.$term.'%');
            if ($reference !== '') {
                $query->orWhere('id', (int) $reference);
            }
        });
    }
}
