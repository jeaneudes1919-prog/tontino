<?php

namespace App\Services;

use App\Enums\LedgerType;
use App\Models\Contribution;
use App\Models\Loan;
use App\Models\LoanGuarantor;
use App\Models\Membership;
use Illuminate\Support\Facades\DB;

class LoanService
{
    public function __construct(private LedgerService $ledger, private LoanScheduleService $schedule, private Notifier $notifier) {}

    public function ceiling(Membership $m): int
    {
        $coop = $m->cooperative;
        $paid = (int) Contribution::where('membership_id', $m->id)->where('status', 'paid')->sum('amount');

        return max((int) $coop->setting('loan_min_amount'), $paid * (int) $coop->setting('loan_max_multiplier'));
    }

    public function request(Membership $m, int $amount, int $months, string $purpose, array $guarantorIds): Loan
    {
        $coop = $m->cooperative;
        if ($amount < (int) $coop->setting('loan_min_amount')) {
            throw new \DomainException('Le montant minimum d\'un prêt est de '.number_format($coop->setting('loan_min_amount'), 0, ',', ' ').' FCFA.');
        }
        if ($amount > $this->ceiling($m)) {
            throw new \DomainException('Montant supérieur à votre plafond ('.number_format($this->ceiling($m), 0, ',', ' ').' FCFA).');
        }
        if (Loan::where('membership_id', $m->id)->whereIn('status', ['requested', 'approved'])->exists()
            || Loan::where('membership_id', $m->id)->where('status', 'disbursed')->whereHas('installments', fn ($q) => $q->where('status', 'late'))->exists()) {
            throw new \DomainException('Vous avez déjà une demande en cours ou un remboursement en retard.');
        }
        $ids = collect($guarantorIds)->unique()->values();
        if ($ids->count() < 1 || $ids->count() > 3 || $ids->contains($m->id)) {
            throw new \DomainException('Choisissez 1 à 3 garants différents de vous.');
        }
        $guarantors = Membership::where('cooperative_id', $m->cooperative_id)->where('status', 'active')->whereIn('id', $ids)->get();
        if ($guarantors->count() !== $ids->count()) {
            throw new \DomainException('Un des garants n\'est pas membre actif de la coopérative.');
        }
        $rate = (float) $coop->setting('default_interest_rate');
        $plan = $this->schedule->build($amount, $rate, $months, now()->addMonth());

        return DB::transaction(function () use ($m, $amount, $months, $purpose, $rate, $plan, $guarantors) {
            $loan = Loan::create(['cooperative_id' => $m->cooperative_id, 'membership_id' => $m->id, 'amount' => $amount, 'interest_rate' => $rate, 'term_months' => $months, 'purpose' => $purpose,
                'status' => 'requested', 'total_interest' => $plan['total_interest'], 'total_due' => $plan['total_due']]);
            foreach ($guarantors as $g) {
                LoanGuarantor::create(['loan_id' => $loan->id, 'membership_id' => $g->id]);
            }
            $this->notifier->send($guarantors, 'Demande de garantie', $m->user->name.' vous demande de garantir un prêt de '.number_format($amount, 0, ',', ' ').' FCFA.', 'loan');
            $this->notifier->send($this->notifier->managers($m->cooperative_id), 'Nouvelle demande de prêt', $m->user->name.' : '.number_format($amount, 0, ',', ' ').' FCFA sur '.$months.' mois.', 'loan');

            return $loan;
        });
    }

    public function guarantee(Loan $loan, Membership $g, bool $accept): void
    {
        $row = LoanGuarantor::where('loan_id', $loan->id)->where('membership_id', $g->id)->first();
        if (! $row || $row->status !== 'pending' || $loan->status !== 'requested') {
            throw new \DomainException('Aucune garantie en attente pour vous sur ce prêt.');
        }
        $row->update(['status' => $accept ? 'accepted' : 'declined']);
        $this->notifier->send([$loan->membership], $accept ? 'Garantie acceptée' : 'Garantie refusée', $g->user->name.' a '.($accept ? 'accepté' : 'refusé').' de se porter garant.', 'loan');
    }

    public function approve(Loan $loan, Membership $by): Loan
    {
        if ($loan->status !== 'requested') {
            throw new \DomainException('Cette demande n\'est plus en attente.');
        }
        if ($loan->membership_id === $by->id) {
            throw new \DomainException('Vous ne pouvez pas approuver votre propre prêt.');
        }
        if ($loan->guarantors()->where('status', '!=', 'accepted')->exists()) {
            throw new \DomainException('Tous les garants doivent avoir accepté avant l\'approbation.');
        }
        $loan->update(['status' => 'approved', 'reviewed_by' => $by->id, 'reviewed_at' => now()]);
        $this->notifier->send([$loan->membership], 'Prêt approuvé', 'Votre prêt de '.number_format($loan->amount, 0, ',', ' ').' FCFA a été approuvé.', 'loan');

        return $loan;
    }

    public function reject(Loan $loan, Membership $by, string $reason): Loan
    {
        if ($loan->status !== 'requested') {
            throw new \DomainException('Cette demande n\'est plus en attente.');
        }
        if ($loan->membership_id === $by->id) {
            throw new \DomainException('Vous ne pouvez pas traiter votre propre prêt.');
        }
        $loan->update(['status' => 'rejected', 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'rejection_reason' => $reason]);
        $this->notifier->send([$loan->membership], 'Prêt refusé', $reason, 'loan');

        return $loan;
    }

    public function disburse(Loan $loan): Loan
    {
        return DB::transaction(function () use ($loan) {
            $loan = Loan::with('membership.cooperative')->lockForUpdate()->findOrFail($loan->id);
            if ($loan->status !== 'approved') {
                throw new \DomainException('Le prêt doit être approuvé avant le décaissement.');
            }
            $fund = $this->ledger->fundOf($loan->membership->cooperative);
            if ($fund->balance < $loan->amount) {
                throw new \DomainException('Fonds insuffisants dans la caisse de la coopérative.');
            }
            $this->ledger->transfer($fund, $this->ledger->accountOf($loan->membership), $loan->amount, LedgerType::LoanDisbursement, [], $loan, 'Décaissement du prêt n°'.$loan->id);
            $plan = $this->schedule->build($loan->amount, (float) $loan->interest_rate, $loan->term_months, now()->addMonth());
            foreach ($plan['installments'] as $row) {
                $loan->installments()->create($row + ['status' => 'pending']);
            }
            $loan->update(['status' => 'disbursed', 'disbursed_at' => now(), 'total_interest' => $plan['total_interest'], 'total_due' => $plan['total_due']]);
            $this->notifier->send([$loan->membership], 'Prêt décaissé', number_format($loan->amount, 0, ',', ' ').' FCFA ont été crédités sur votre portefeuille.', 'loan');

            return $loan;
        });
    }

    /** Rembourse avec le solde du portefeuille : retards d'abord, puis échéances les plus anciennes. */
    public function repay(Loan $loan, int $amount, Membership $by): Loan
    {
        return DB::transaction(function () use ($loan, $amount) {
            $loan = Loan::with('membership.cooperative')->lockForUpdate()->findOrFail($loan->id);
            if ($loan->status !== 'disbursed') {
                throw new \DomainException('Ce prêt n\'est pas en cours de remboursement.');
            }
            if ($amount <= 0) {
                throw new \DomainException('Montant invalide.');
            }
            $wallet = $this->ledger->accountOf($loan->membership);
            $fund = $this->ledger->fundOf($loan->membership->cooperative);
            $left = $amount;
            $installments = $loan->installments()->whereIn('status', ['pending', 'partial', 'late'])->get()->sortBy(fn ($i) => [$i->status === 'late' ? 0 : 1, $i->due_date->timestamp])->values();
            foreach ($installments as $i) {
                if ($left <= 0) {
                    break;
                }
                $pay = min($left, $i->remaining());
                $due = $i->principal + $i->interest;
                $interestPart = $due ? (int) round($pay * $i->interest / $due) : 0;
                if ($pay - $interestPart > 0) {
                    $this->ledger->transfer($wallet, $fund, $pay - $interestPart, LedgerType::LoanRepayment, [], $loan, 'Remboursement prêt n°'.$loan->id);
                }
                if ($interestPart > 0) {
                    $this->ledger->transfer($wallet, $fund, $interestPart, LedgerType::Interest, [], $loan, 'Intérêts prêt n°'.$loan->id);
                }
                $i->paid_amount += $pay;
                $i->status = $i->paid_amount >= $due ? 'paid' : 'partial';
                $i->paid_at = $i->status === 'paid' ? now() : null;
                $i->save();
                $left -= $pay;
            }
            if ($left === $amount) {
                throw new \DomainException('Aucune échéance à rembourser.');
            }
            if (! $loan->installments()->where('status', '!=', 'paid')->exists()) {
                $loan->update(['status' => 'repaid']);
                $this->notifier->send([$loan->membership], 'Prêt soldé', 'Félicitations, votre prêt est intégralement remboursé.', 'loan');
            }

            return $loan;
        });
    }

    public function flagLate(): int
    {
        $n = 0;
        foreach (\App\Models\LoanInstallment::whereIn('status', ['pending', 'partial'])->whereDate('due_date', '<', today())->with('loan.membership')->get() as $i) {
            $i->update(['status' => 'late']);
            $n++;
            if ($i->due_date->lt(today()->subDays(90)) && $i->loan->status === 'disbursed') {
                $i->loan->update(['status' => 'defaulted']);
            }
            $this->notifier->send([$i->loan->membership], 'Échéance en retard', 'L\'échéance n°'.$i->number.' de votre prêt est en retard.', 'loan');
        }

        return $n;
    }
}
