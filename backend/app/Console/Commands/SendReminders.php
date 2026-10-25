<?php

namespace App\Console\Commands;

use App\Models\Contribution;
use App\Models\LoanInstallment;
use App\Services\Notifier;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    protected $signature = 'notifications:reminders';

    protected $description = 'Rappels J-3 des cotisations et des échéances de prêt';

    public function handle(Notifier $notifier): int
    {
        $n = 0;
        foreach (Contribution::where('status', 'pending')->whereDate('due_date', today()->addDays(3))->with('membership.user', 'cycle.tontine')->get() as $c) {
            $notifier->send([$c->membership], 'Rappel de cotisation', 'Votre cotisation « '.$c->cycle->tontine->name.' » est due dans 3 jours.', 'contribution');
            $n++;
        }
        foreach (LoanInstallment::whereIn('status', ['pending', 'partial'])->whereDate('due_date', today()->addDays(3))->with('loan.membership.user')->get() as $i) {
            $notifier->send([$i->loan->membership], 'Rappel d\'échéance', 'L\'échéance n°'.$i->number.' de votre prêt est due dans 3 jours.', 'loan');
            $n++;
        }
        $this->info("$n rappel(s) envoyé(s).");

        return self::SUCCESS;
    }
}
