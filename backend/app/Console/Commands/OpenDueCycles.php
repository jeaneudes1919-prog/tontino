<?php

namespace App\Console\Commands;

use App\Models\Cycle;
use App\Services\Notifier;
use Illuminate\Console\Command;

class OpenDueCycles extends Command
{
    protected $signature = 'cycles:open-due';

    protected $description = 'Ouvre les cycles dont l\'échéance est dans 3 jours ou moins et prévient les membres';

    public function handle(Notifier $notifier): int
    {
        $n = 0;
        foreach (Cycle::where('status', 'upcoming')->whereDate('due_date', '<=', today()->addDays(3))->whereHas('tontine', fn ($q) => $q->where('status', 'active'))->with('tontine', 'contributions.membership.user')->get() as $c) {
            $c->update(['status' => 'open']);
            $notifier->send($c->contributions->pluck('membership'), 'Cotisation à venir', 'Cotisation « '.$c->tontine->name.' » attendue le '.$c->due_date->format('d/m/Y').'.', 'contribution');
            $n++;
        }
        $this->info("$n cycle(s) ouvert(s).");

        return self::SUCCESS;
    }
}
