<?php

namespace App\Services;

use App\Enums\LedgerType;
use App\Models\Cycle;
use Illuminate\Support\Facades\DB;

class PayoutService
{
    public function __construct(private LedgerService $ledger, private Notifier $notifier) {}

    public function payout(Cycle $cycle, bool $force = false, ?string $reason = null): Cycle
    {
        return DB::transaction(function () use ($cycle, $force, $reason) {
            $cycle = Cycle::with('tontine', 'beneficiary.user')->lockForUpdate()->findOrFail($cycle->id);
            if ($cycle->status === 'paid_out') {
                throw new \DomainException('Ce cycle a déjà été versé.');
            }
            if ($cycle->status !== 'ready' && ! ($force && $reason)) {
                throw new \DomainException('Toutes les cotisations doivent être réglées avant le versement (ou indiquez un motif pour forcer).');
            }
            if (! $cycle->beneficiary || $cycle->collected_amount <= 0) {
                throw new \DomainException('Aucun bénéficiaire ou aucun montant à verser pour ce cycle.');
            }
            $this->ledger->transfer($this->ledger->potOf($cycle->tontine), $this->ledger->accountOf($cycle->beneficiary), $cycle->collected_amount, LedgerType::Payout, ['forced' => $force, 'reason' => $reason], $cycle, "Cagnotte {$cycle->tontine->name} n°{$cycle->number}");
            $cycle->update(['status' => 'paid_out', 'paid_out_at' => now()]);
            if (! $cycle->tontine->cycles()->where('status', '!=', 'paid_out')->exists()) {
                $cycle->tontine->update(['status' => 'completed']);
            }
            $this->notifier->send([$cycle->beneficiary], 'Cagnotte reçue !', 'Vous avez reçu '.number_format($cycle->collected_amount, 0, ',', ' ').' FCFA ('.$cycle->tontine->name.').', 'payout');

            return $cycle;
        });
    }
}
