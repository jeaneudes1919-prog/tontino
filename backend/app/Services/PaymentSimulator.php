<?php

namespace App\Services;

use App\Enums\LedgerType;
use App\Jobs\ProcessPayment;
use App\Models\Contribution;
use App\Models\Loan;
use App\Models\Membership;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentSimulator
{
    public function __construct(private LedgerService $ledger, private ContributionService $contributions, private LoanService $loans) {}

    public function initiate(Membership $m, string $purpose, string $provider, string $phone, int $amount, ?Model $related = null): Payment
    {
        $p = Payment::create([
            'cooperative_id' => $m->cooperative_id, 'membership_id' => $m->id, 'purpose' => $purpose, 'provider' => $provider, 'phone' => $phone,
            'amount' => $amount, 'status' => 'pending', 'reference' => 'PAY-'.strtoupper(Str::random(10)),
            'related_type' => $related ? class_basename($related) : null, 'related_id' => $related?->getKey(),
        ]);
        ProcessPayment::dispatch($p->id)->delay(now()->addSeconds(4));

        return $p;
    }

    /** Traite un paiement en attente. Échec simulé si le numéro se termine par 00. Idempotent. */
    public function process(Payment $payment): Payment
    {
        try {
            return DB::transaction(function () use ($payment) {
                $p = Payment::lockForUpdate()->findOrFail($payment->id);
                if ($p->status !== 'pending') {
                    return $p;
                }
                if (str_ends_with(preg_replace('/\D/', '', $p->phone), '00')) {
                    $p->update(['status' => 'failed', 'failure_reason' => 'Solde insuffisant chez l\'opérateur', 'completed_at' => now()]);

                    return $p;
                }
                $m = Membership::findOrFail($p->membership_id);
                $this->ledger->credit($this->ledger->accountOf($m), $p->amount, LedgerType::Deposit, ['reference' => $p->reference, 'provider' => $p->provider], $p, 'Dépôt Mobile Money '.strtoupper($p->provider));
                if ($p->purpose === 'contribution') {
                    $this->contributions->payFromWallet(Contribution::findOrFail($p->related_id));
                } elseif ($p->purpose === 'loan_repayment') {
                    $this->loans->repay(Loan::findOrFail($p->related_id), $p->amount, $m);
                }
                $p->update(['status' => 'success', 'completed_at' => now()]);

                return $p;
            });
        } catch (\Throwable $e) {
            Payment::whereKey($payment->id)->update(['status' => 'failed', 'failure_reason' => $e->getMessage(), 'completed_at' => now()]);

            return Payment::find($payment->id);
        }
    }
}
