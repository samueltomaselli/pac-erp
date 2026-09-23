<?php

namespace App\Models;

use App\Enums\ProposalItemType;
use Database\Factories\ProposalCatalogItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProposalCatalogItem extends Model
{
    /** @use HasFactory<ProposalCatalogItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'type',
        'default_quantity',
        'default_unit_amount_cents',
        'allows_installments',
        'is_active',
        'sort_order',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'recurring',
        'default_quantity' => 1,
        'allows_installments' => false,
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProposalItemType::class,
            'default_quantity' => 'integer',
            'default_unit_amount_cents' => 'integer',
            'allows_installments' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<ProposalItem, $this>
     */
    public function proposalItems(): HasMany
    {
        return $this->hasMany(ProposalItem::class, 'catalog_item_id');
    }

    /**
     * @param  Builder<ProposalCatalogItem>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }
}
