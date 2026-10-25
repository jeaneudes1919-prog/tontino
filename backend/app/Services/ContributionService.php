<?php

namespace App\Services;

use App\Enums\LedgerType;
use App\Models\Contribution;
use App\Models\Cycle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ContributionService
{
    public function __construct(private LedgerService $ledger, private Notifier $notifier) {}

    /** Paie une cotisation avec le solde du portefeuille. Idempotent : sans effet si déjà payée. */
    public function payFromWallet(Contribution $c, ?Carbon $at = null): Contribution
    {
        return DB::transaction(function () use ($c, $at) {
            $c = Contribution::with('cycle.tontine.cooperative', 'membership')->lockForUpdate()->findOrFail($c->id);
            if (in_array($c->status, ['paid', 'waived'], true)) {
                return $c;
            }
            $cycle = $c->cycle;
            $tontine = $cycle->tontine;
            $wallet = $this->ledger->accountOf($c->membership);
            $label = "Cotisation {$tontine->name} n°{$cycle->number}";
            $this->ledger->transfer($wallet, $this->ledger->potOf($tontine), $c->amount, LedgerType::Contribution, [], $c, $label);
            if ($c->penalty_amount > 0) {
                $this->ledger->transfer($wallet, $this->ledger->fundOf($tontine->cooperative), $c->penalty_amount, LedgerType::Penalty, [], $c, "Pénalité de retard : $label");
            }
            $c->update(['status' => 'paid', 'paid_at' => $at ?? now()]);
            $cycle->increment('collected_amount', $c->amount);
            $this->refreshCycle($cycle->refresh());
            $this->notifier->send($this->notifier->managers($tontine->cooperative_id), 'Cotisation reçue', $c->membership->user->name.' a payé '.number_format($c->amount, 0, ',', ' ').' FCFA ('.$tontine->name.').', 'contribution');

            return $c;
        });
    }

    public function refreshCycle(Cycle $cycle): void
    {
        $open = $cycle->contributions()->whereIn('status', ['pending', 'late'])->count();
        if ($open === 0 && in_array($cycle->status, ['upcoming', 'open'], true)) {
            $cycle->update(['status' => 'ready']);
        }
    }

    public function markLate(Contribution $c): void
    {
        $percent = $c->cycle->tontine->penalty_percent;
        $c->update(['status' => 'late', 'penalty_amount' => (int) round($c->amount * $percent / 100)]);
    }

    /** Passe en retard toutes les cotisations échues et calcule la pénalité. */
    public function flagLate(): int
    {
        $n = 0;
        Contribution::where('status', 'pending')->whereDate('due_date', '<', today())->with('cycle.tontine')->each(function ($c) use (&$n) {
            $this->markLate($c);
            $n++;
        });

        return $n;
    }

    public function waive(Contribution $c): void
    {
        $c->update(['status' => 'waived']);
        $this->refreshCycle($c->cycle);
    }
}
