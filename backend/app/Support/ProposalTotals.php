<?php

namespace App\Support;

use App\Enums\ProposalItemType;
use App\Models\ProposalItem;

class ProposalTotals
{
    /**
     * @param  iterable<ProposalItem>  $items
     * @return array<string, int>
     */
    public static function summarize(iterable $items): array
    {
        $mrr = 0;
        $oneTimeSingle = 0;
        $oneTimeInstallments = 0;
        $installmentMonthly = 0;
        $maxInstallments = 0;

        foreach ($items as $item) {
            $line = $item->line_total_cents;

            if ($item->type === ProposalItemType::Recurring) {
                $mrr += $line;

                continue;
            }

            if ($item->installments > 1) {
                $oneTimeInstallments += $line;
                $installmentMonthly += $item->installment_amount_cents;
                $maxInstallments = max($maxInstallments, $item->installments);

                continue;
            }

            $oneTimeSingle += $line;
        }

        return [
            'total_cents' => $mrr + $oneTimeSingle + $oneTimeInstallments,
            'mrr_cents' => $mrr,
            'onetime_cents' => $oneTimeSingle + $oneTimeInstallments,
            'onetime_single_cents' => $oneTimeSingle,
            'installment_monthly_cents' => $installmentMonthly,
            'max_installments' => $maxInstallments,
            'first_payment_cents' => $mrr + $installmentMonthly + $oneTimeSingle,
            'during_installments_cents' => $mrr + $installmentMonthly,
        ];
    }
}
