<?php

namespace App\Models;

use App\Enums\ProposalItemType;
use Database\Factories\ProposalItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalItem extends Model
{
    /** @use HasFactory<ProposalItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'proposal_id',
        'catalog_item_id',
        'type',
        'description',
        'quantity',
        'unit_amount_cents',
        'discount_cents',
        'installments',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProposalItemType::class,
        ];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(ProposalCatalogItem::class, 'catalog_item_id');
    }

    public function getLineTotalCentsAttribute(): int
    {
        $lineTotal = ($this->quantity * $this->unit_amount_cents) - $this->discount_cents;

        return max(0, $lineTotal);
    }

    public function getInstallmentAmountCentsAttribute(): int
    {
        if ($this->installments <= 1) {
            return $this->line_total_cents;
        }

        return intdiv($this->line_total_cents, $this->installments);
    }

    public function getLastInstallmentCentsAttribute(): int
    {
        if ($this->installments <= 1) {
            return $this->line_total_cents;
        }

        return $this->line_total_cents - (($this->installments - 1) * $this->installment_amount_cents);
    }
}
