<?php

namespace Tests\Unit\Support;

use App\Enums\ProposalItemType;
use App\Models\ProposalItem;
use App\Support\ProposalTotals;
use Tests\TestCase;

class ProposalTotalsTest extends TestCase
{
    public function test_a_proposal_without_items_is_all_zero(): void
    {
        $this->assertSame([
            'total_cents' => 0,
            'mrr_cents' => 0,
            'onetime_cents' => 0,
            'onetime_single_cents' => 0,
            'installment_monthly_cents' => 0,
            'max_installments' => 0,
            'first_payment_cents' => 0,
            'during_installments_cents' => 0,
        ], ProposalTotals::summarize([]));
    }

    public function test_lines_are_quantity_times_unit_amount(): void
    {
        $totals = ProposalTotals::summarize([
            $this->item(ProposalItemType::OneTime, quantity: 2, unit: 25000),
            $this->item(ProposalItemType::OneTime, quantity: 1, unit: 100000),
        ]);

        $this->assertSame(150000, $totals['total_cents']);
    }

    public function test_discount_reduces_the_line(): void
    {
        $item = $this->item(ProposalItemType::OneTime, quantity: 2, unit: 10000, discount: 2500);

        $this->assertSame(17500, $item->line_total_cents);
        $this->assertSame(17500, ProposalTotals::summarize([$item])['total_cents']);
    }

    public function test_discount_equal_to_the_gross_amount_zeroes_the_line(): void
    {
        $item = $this->item(ProposalItemType::OneTime, quantity: 3, unit: 10000, discount: 30000);

        $this->assertSame(0, $item->line_total_cents);
        $this->assertSame(0, ProposalTotals::summarize([$item])['total_cents']);
    }

    public function test_recurring_and_one_time_are_split(): void
    {
        $totals = ProposalTotals::summarize([
            $this->item(ProposalItemType::Recurring, quantity: 1, unit: 50000),
            $this->item(ProposalItemType::Recurring, quantity: 2, unit: 10000),
            $this->item(ProposalItemType::OneTime, quantity: 1, unit: 80000),
        ]);

        $this->assertSame(70000, $totals['mrr_cents']);
        $this->assertSame(80000, $totals['onetime_cents']);
        $this->assertSame(150000, $totals['total_cents']);
    }

    public function test_the_last_installment_absorbs_the_remainder(): void
    {
        $item = $this->item(ProposalItemType::OneTime, quantity: 1, unit: 100, installments: 3);

        $this->assertSame(33, $item->installment_amount_cents);
        $this->assertSame(34, $item->last_installment_cents);
        $this->assertSame(100, $item->installment_amount_cents * 2 + $item->last_installment_cents);

        $totals = ProposalTotals::summarize([$item]);

        $this->assertSame(33, $totals['installment_monthly_cents']);
        $this->assertSame(100, $totals['onetime_cents']);
        $this->assertSame(0, $totals['onetime_single_cents']);
    }

    public function test_installments_never_lose_a_cent(): void
    {
        foreach ([[100000, 3], [99999, 7], [1, 12], [0, 5], [123457, 12]] as [$amount, $installments]) {
            $item = $this->item(ProposalItemType::OneTime, quantity: 1, unit: $amount, installments: $installments);

            $sum = $item->installment_amount_cents * ($installments - 1) + $item->last_installment_cents;

            $this->assertSame($amount, $sum, "{$amount} em {$installments}x");
        }
    }

    public function test_first_payment_and_during_installments_with_every_kind_of_line(): void
    {
        $totals = ProposalTotals::summarize([
            $this->item(ProposalItemType::Recurring, quantity: 1, unit: 50000),
            $this->item(ProposalItemType::OneTime, quantity: 1, unit: 40000),
            $this->item(ProposalItemType::OneTime, quantity: 1, unit: 60000, installments: 3),
        ]);

        $this->assertSame([
            'total_cents' => 150000,
            'mrr_cents' => 50000,
            'onetime_cents' => 100000,
            'onetime_single_cents' => 40000,
            'installment_monthly_cents' => 20000,
            'max_installments' => 3,
            'first_payment_cents' => 110000,
            'during_installments_cents' => 70000,
        ], $totals);
    }

    public function test_max_installments_is_the_largest_among_installment_items(): void
    {
        $totals = ProposalTotals::summarize([
            $this->item(ProposalItemType::OneTime, quantity: 1, unit: 30000, installments: 3),
            $this->item(ProposalItemType::OneTime, quantity: 1, unit: 60000, installments: 6),
            $this->item(ProposalItemType::OneTime, quantity: 1, unit: 10000),
        ]);

        $this->assertSame(6, $totals['max_installments']);
        $this->assertSame(10000 + 10000, $totals['installment_monthly_cents']);
    }

    public function test_max_installments_is_zero_without_installment_items(): void
    {
        $totals = ProposalTotals::summarize([
            $this->item(ProposalItemType::Recurring, quantity: 1, unit: 30000),
            $this->item(ProposalItemType::OneTime, quantity: 1, unit: 10000),
        ]);

        $this->assertSame(0, $totals['max_installments']);
    }

    private function item(ProposalItemType $type, int $quantity, int $unit, int $discount = 0, int $installments = 1): ProposalItem
    {
        return new ProposalItem([
            'type' => $type,
            'description' => 'Item',
            'quantity' => $quantity,
            'unit_amount_cents' => $unit,
            'discount_cents' => $discount,
            'installments' => $installments,
        ]);
    }
}
