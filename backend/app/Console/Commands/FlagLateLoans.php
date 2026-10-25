<?php

namespace App\Console\Commands;

use App\Services\LoanService;
use Illuminate\Console\Command;

class FlagLateLoans extends Command
{
    protected $signature = 'loans:flag-late';

    protected $description = 'Marque les échéances de prêt en retard';

    public function handle(LoanService $service): int
    {
        $this->info($service->flagLate().' échéance(s) en retard.');

        return self::SUCCESS;
    }
}
