<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class LoanScheduleService
{
    /** Échéancier à annuité constante. La dernière échéance solde exactement le capital. */
    public function build(int $principal, float $annualRate, int $months, Carbon $firstDue): array
    {
        $r = $annualRate / 12 / 100;
        $pay = $r > 0 ? $principal * $r / (1 - pow(1 + $r, -$months)) : $principal / $months;
        $balance = $principal;
        $rows = [];
        $totalInterest = 0;
        for ($i = 1; $i <= $months; $i++) {
            $interest = (int) round($balance * $r);
            $princ = $i < $months ? min($balance, max(0, (int) round($pay) - $interest)) : $balance;
            $rows[] = ['number' => $i, 'due_date' => $firstDue->copy()->addMonthsNoOverflow($i - 1)->toDateString(), 'principal' => $princ, 'interest' => $interest];
            $balance -= $princ;
            $totalInterest += $interest;
        }

        return ['installments' => $rows, 'monthly' => (int) round($pay), 'total_interest' => $totalInterest, 'total_due' => $principal + $totalInterest];
    }
}
