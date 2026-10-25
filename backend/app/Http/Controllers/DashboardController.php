<?php

namespace App\Http\Controllers;

use App\Models\Contribution;
use App\Models\Loan;
use App\Models\Membership;
use App\Models\Payment;
use App\Services\LedgerService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $r, \App\Models\Cooperative $coop, LedgerService $ledger)
    {
        $m = $r->attributes->get('membership');
        $wallet = $ledger->accountOf($m);
        $mine = Contribution::where('membership_id', $m->id);
        $out = [
            'role' => $m->role->value,
            'wallet_balance' => $wallet->balance,
            'dues' => (clone $mine)->whereIn('status', ['pending', 'late'])->with('cycle.tontine')->orderBy('due_date')->take(6)->get()->map(fn ($c) => ['id' => $c->id, 'tontine' => $c->cycle->tontine->name, 'cycle' => $c->cycle->number, 'due_date' => $c->due_date->toDateString(), 'amount' => $c->total(), 'status' => $c->status]),
            'punctuality' => $this->punctuality($m),
            'loans' => Loan::where('membership_id', $m->id)->whereIn('status', ['requested', 'approved', 'disbursed'])->get(['id', 'amount', 'status', 'total_due']),
            'recent' => $wallet->entries()->take(6)->get(),
        ];
        if ($m->isManager()) {
            $fund = $ledger->fundOf($coop);
            $coopContribs = Contribution::whereHas('cycle.tontine', fn ($q) => $q->where('cooperative_id', $coop->id));
            $monthly = collect(range(11, 0))->map(function ($i) use ($coopContribs) {
                $d = now()->subMonths($i);

                return ['month' => $d->translatedFormat('M y'), 'collected' => (int) (clone $coopContribs)->where('status', 'paid')->whereYear('paid_at', $d->year)->whereMonth('paid_at', $d->month)->sum('amount')];
            });
            $due = (clone $coopContribs)->whereDate('due_date', '<=', today())->whereIn('status', ['paid', 'pending', 'late'])->count();
            $out['manager'] = [
                'fund_balance' => $fund->balance,
                'members' => Membership::where('cooperative_id', $coop->id)->where('status', 'active')->count(),
                'collected_this_month' => (int) (clone $coopContribs)->where('status', 'paid')->whereYear('paid_at', now()->year)->whereMonth('paid_at', now()->month)->sum('amount'),
                'contribution_rate' => $due ? (int) round(100 * (clone $coopContribs)->whereDate('due_date', '<=', today())->where('status', 'paid')->count() / $due) : 100,
                'late_contributions' => (clone $coopContribs)->where('status', 'late')->count(),
                'loans_active' => Loan::where('cooperative_id', $coop->id)->where('status', 'disbursed')->count(),
                'loans_pending' => Loan::where('cooperative_id', $coop->id)->where('status', 'requested')->count(),
                'loans_by_status' => Loan::where('cooperative_id', $coop->id)->selectRaw('status, count(*) n, sum(amount) total')->groupBy('status')->get(),
                'monthly' => $monthly,
                'pending_payments' => Payment::where('cooperative_id', $coop->id)->where('status', 'pending')->count(),
            ];
        }

        return response()->json($out);
    }

    private function punctuality(Membership $m): int
    {
        $total = Contribution::where('membership_id', $m->id)->whereIn('status', ['paid', 'late'])->count();
        if (! $total) {
            return 100;
        }

        return (int) round(100 * Contribution::where('membership_id', $m->id)->where('status', 'paid')->where('penalty_amount', 0)->count() / $total);
    }
}
