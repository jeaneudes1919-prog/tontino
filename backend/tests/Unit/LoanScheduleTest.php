<?php

namespace Tests\Unit;

use App\Services\LoanScheduleService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class LoanScheduleTest extends TestCase
{
    public function test_principal_is_fully_repaid_and_last_installment_is_adjusted(): void
    {
        $plan = (new LoanScheduleService)->build(100000, 12, 12, Carbon::parse('2026-01-15'));
        $this->assertCount(12, $plan['installments']);
        $this->assertSame(100000, array_sum(array_column($plan['installments'], 'principal')));
        $this->assertSame($plan['total_interest'], array_sum(array_column($plan['installments'], 'interest')));
        $this->assertSame(100000 + $plan['total_interest'], $plan['total_due']);
    }

    public function test_twelve_months_at_twelve_percent_gives_expected_monthly_payment(): void
    {
        $plan = (new LoanScheduleService)->build(100000, 12, 12, Carbon::parse('2026-01-15'));
        $this->assertEqualsWithDelta(8885, $plan['monthly'], 1);
    }

    public function test_zero_rate_splits_principal_evenly_without_interest(): void
    {
        $plan = (new LoanScheduleService)->build(90000, 0, 3, Carbon::parse('2026-01-15'));
        $this->assertSame(0, $plan['total_interest']);
        $this->assertSame([30000, 30000, 30000], array_column($plan['installments'], 'principal'));
    }
}
