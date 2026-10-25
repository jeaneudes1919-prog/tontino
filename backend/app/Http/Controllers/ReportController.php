<?php

namespace App\Http\Controllers;

use App\Models\Contribution;
use App\Models\Cooperative;
use App\Models\LedgerEntry;
use App\Models\Loan;
use App\Models\Membership;
use App\Services\LedgerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function __construct(private LedgerService $ledger) {}

    public function statement(Request $r, Cooperative $coop, Membership $membership)
    {
        $me = $r->attributes->get('membership');
        abort_unless($me->isManager() || $me->id === $membership->id, 403, 'Relevé réservé au titulaire ou aux responsables.');
        $from = $r->filled('from') ? Carbon::parse($r->query('from'))->startOfDay() : now()->subMonths(3)->startOfDay();
        $to = $r->filled('to') ? Carbon::parse($r->query('to'))->endOfDay() : now()->endOfDay();
        $account = $this->ledger->accountOf($membership);
        $pdf = Pdf::loadView('pdf.statement', [
            'coop' => $coop, 'm' => $membership->load('user'), 'balance' => $account->balance, 'from' => $from, 'to' => $to,
            'entries' => $account->entries()->whereBetween('created_at', [$from, $to])->get()->reverse()->values(),
            'contributions' => Contribution::with('cycle.tontine')->where('membership_id', $membership->id)->whereBetween('due_date', [$from, $to])->orderBy('due_date')->get(),
            'loans' => Loan::where('membership_id', $membership->id)->get(),
        ]);

        return $pdf->download('releve-'.$membership->member_number.'.pdf');
    }

    public function summary(Request $r, Cooperative $coop)
    {
        $month = $r->filled('month') ? Carbon::createFromFormat('Y-m', $r->query('month'))->startOfMonth() : now()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $entries = LedgerEntry::whereIn('account_id', \App\Models\Account::where('cooperative_id', $coop->id)->pluck('id'))->whereBetween('created_at', [$month, $end]);
        $contribs = Contribution::whereHas('cycle.tontine', fn ($q) => $q->where('cooperative_id', $coop->id));
        $pdf = Pdf::loadView('pdf.summary', [
            'coop' => $coop, 'month' => $month, 'fund' => $this->ledger->fundOf($coop)->balance,
            'collected' => (int) (clone $contribs)->where('status', 'paid')->whereBetween('paid_at', [$month, $end])->sum('amount'),
            'late' => (clone $contribs)->where('status', 'late')->count(),
            'payouts' => (int) (clone $entries)->where('type', 'payout')->where('direction', 'credit')->sum('amount'),
            'disbursed' => (int) (clone $entries)->where('type', 'loan_disbursement')->where('direction', 'credit')->sum('amount'),
            'repaid' => (int) (clone $entries)->whereIn('type', ['loan_repayment', 'interest'])->where('direction', 'credit')->sum('amount'),
            'loans' => Loan::with('membership.user')->where('cooperative_id', $coop->id)->whereIn('status', ['disbursed', 'defaulted'])->get(),
            'members' => Membership::where('cooperative_id', $coop->id)->where('status', 'active')->count(),
        ]);

        return $pdf->download('synthese-'.$month->format('Y-m').'.pdf');
    }

    public function contributionsCsv(Cooperative $coop)
    {
        $rows = Contribution::with(['cycle.tontine', 'membership.user'])->whereHas('cycle.tontine', fn ($q) => $q->where('cooperative_id', $coop->id))->orderBy('due_date')->get();

        return $this->csv('cotisations.csv', ['Tontine', 'Cycle', 'Membre', 'Échéance', 'Montant', 'Pénalité', 'Statut', 'Payée le'], $rows->map(fn ($c) => [$c->cycle->tontine->name, $c->cycle->number, $c->membership->user->name, $c->due_date->format('d/m/Y'), $c->amount, $c->penalty_amount, $c->status, $c->paid_at?->format('d/m/Y H:i')]));
    }

    public function ledgerCsv(Cooperative $coop)
    {
        $accounts = \App\Models\Account::where('cooperative_id', $coop->id)->get()->keyBy('id');
        $rows = LedgerEntry::whereIn('account_id', $accounts->keys())->orderBy('id')->get();

        return $this->csv('grand-livre.csv', ['Date', 'Compte', 'Sens', 'Type', 'Montant', 'Solde après', 'Libellé'], $rows->map(fn ($e) => [Carbon::parse($e->created_at)->format('d/m/Y H:i'), $accounts[$e->account_id]->type.'#'.$accounts[$e->account_id]->owner_id, $e->direction === 'credit' ? 'Crédit' : 'Débit', $e->type, $e->amount, $e->balance_after, $e->description]));
    }

    private function csv(string $name, array $head, $rows)
    {
        return response()->streamDownload(function () use ($head, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $head, ';');
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
