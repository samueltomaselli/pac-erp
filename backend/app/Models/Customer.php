<?php
//Customer
namespace App\Models;

use App\Enums\CustomerSegment;
use App\Enums\CustomerStatus;
use App\Enums\TaskStatus;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'document',
        'email',
        'phone',
        'contact_name',
        'segment',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'segment' => CustomerSegment::class,
            'status' => CustomerStatus::class,
        ];
    }

    protected function documentFormatted(): Attribute
    {
        return Attribute::get(function (): string {
            $digits = (string) $this->document;

            return match (strlen($digits)) {
                11 => vsprintf('%s%s%s.%s%s%s.%s%s%s-%s%s', str_split($digits)),
                14 => vsprintf('%s%s.%s%s%s.%s%s%s/%s%s%s%s-%s%s', str_split($digits)),
                default => $digits,
            };
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return HasMany<Proposal, $this> */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function pendingTasks(): HasMany
    {
        return $this->tasks()->where('status', TaskStatus::Pending);
    }

    /**
     * @param  Builder<Customer>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $documentTerm = preg_replace('/\D/', '', $term);

        $query->where(function (Builder $query) use ($term, $documentTerm) {
            $query->where('name', 'like', '%'.$term.'%');

            if ($documentTerm !== '') {
                $query->orWhere('document', 'like', '%'.$documentTerm.'%');
            }
        });
    }

    /**
     * @param  Builder<Customer>  $query
     */
    public function scopeStatus(Builder $query, CustomerStatus|string|null $status): void
    {
        if ($status === null || $status === '') {
            return;
        }

        $query->where('status', $status instanceof CustomerStatus ? $status->value : $status);
    }
}
