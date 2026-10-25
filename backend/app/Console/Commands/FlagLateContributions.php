<?php

namespace App\Console\Commands;

use App\Services\ContributionService;
use Illuminate\Console\Command;

class FlagLateContributions extends Command
{
    protected $signature = 'contributions:flag-late';

    protected $description = 'Passe en retard les cotisations échues et calcule les pénalités';

    public function handle(ContributionService $service): int
    {
        $this->info($service->flagLate().' cotisation(s) en retard.');

        return self::SUCCESS;
    }
}
