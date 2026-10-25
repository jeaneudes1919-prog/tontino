<?php

namespace App\Http\Controllers;

use App\Models\Cooperative;
use App\Models\Loan;
use App\Policies\LoanPolicy;
use App\Services\LoanScheduleService;
use App\Services\LoanService;
use App\Services\PaymentSimulator;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function __construct(private LoanService $service, private LoanPolicy $policy) {}

    public function index(Request $r, Cooperative $coop)
    {
        $m = $r->attributes->get('membership');
        $q = Loan::with('membership.user')->where('cooperative_id', $coop->id)->latest();
        if (! $m->isManager() || $r->boolean('mine')) {
            $q->where(fn ($w) => $w->where('membership_id', $m->id)->orWhereHas('guarantors', fn ($g) => $g->where('membership_id', $m->id)));
        }
        if ($s = $r->query('status')) {
            $q->where('status', $s);
        }

        return response()->json($q->get()->map(fn ($l) => $this->summary($l, $m)));
    }

    public function simulate(Request $r, Cooperative $coop, LoanScheduleService $schedule)
    {
        $d = $r->validate(['amount' => 'required|integer|min:1000|max:50000000', 'term_months' => 'required|integer|min:1|max:36', 'rate' => 'nullable|numeric|min:0|max:60']);
        $rate = (float) ($d['rate'] ?? $coop->setting('default_interest_rate'));

        return response()->json(['rate' => $rate, 'ceiling' => $this->service->ceiling($r->attributes->get('membership'))] + $schedule->build($d['amount'], $rate, $d['term_months'], now()->addMonth()));
    }

    public function store(Request $r)
    {
        $d = $r->validate(['amount' => 'required|integer|min:1000', 'term_months' => 'required|integer|min:1|max:36', 'purpose' => 'required|string|min:5|max:200', 'guarantor_ids' => 'required|array|min:1|max:3', 'guarantor_ids.*' => 'integer']);
        $loan = $this->service->request($r->attributes->get('membership'), $d['amount'], $d['term_months'], $d['purpose'], $d['guarantor_ids']);

        return response()->json($loan, 201);
    }

    public function show(Request $r, Cooperative $coop, Loan $loan)
    {
        $m = $r->attributes->get('membership');
        abort_unless($this->policy->view($m, $loan), 403, 'Accès refusé à ce prêt.');
        $loan->load('membership.user', 'reviewer.user', 'guarantors.membership.user', 'installments');

        return response()->json($this->summary($loan, $m) + [
            'guarantors' => $loan->guarantors->map(fn ($g) => ['membership_id' => $g->membership_id, 'name' => $g->membership->user->name, 'status' => $g->status]),
            'installments' => $loan->installments, 'reviewer' => $loan->reviewer?->user->name, 'rejection_reason' => $loan->rejection_reason, 'reviewed_at' => $loan->reviewed_at,
            'can_review' => $this->policy->review($m, $loan) && $loan->status === 'requested',
        ]);
    }

    public function guarantee(Request $r, Cooperative $coop, Loan $loan)
    {
        $d = $r->validate(['accept' => 'required|boolean']);
        $this->service->guarantee($loan, $r->attributes->get('membership'), $d['accept']);

        return response()->json(['ok' => true]);
    }

    public function approve(Request $r, Cooperative $coop, Loan $loan)
    {
        return response()->json($this->service->approve($loan, $r->attributes->get('membership')));
    }

    public function reject(Request $r, Cooperative $coop, Loan $loan)
    {
        $d = $r->validate(['reason' => 'required|string|min:3|max:200']);

        return response()->json($this->service->reject($loan, $r->attributes->get('membership'), $d['reason']));
    }

    public function disburse(Cooperative $coop, Loan $loan)
    {
        return response()->json($this->service->disburse($loan));
    }

    public function repay(Request $r, Cooperative $coop, Loan $loan, PaymentSimulator $sim)
    {
        $m = $r->attributes->get('membership');
        abort_unless($loan->membership_id === $m->id, 403, 'Seul l\'emprunteur peut rembourser ce prêt.');
        $d = $r->validate(['amount' => 'required|integer|min:100', 'source' => 'required|in:wallet,mobile_money', 'provider' => 'required_if:source,mobile_money|in:mtn,moov,celtiis', 'phone' => 'required_if:source,mobile_money|nullable|string|min:8|max:20']);
        if ($d['source'] === 'wallet') {
            return response()->json($this->service->repay($loan, $d['amount'], $m));
        }

        return response()->json($sim->initiate($m, 'loan_repayment', $d['provider'], $d['phone'], $d['amount'], $loan), 202);
    }

    private function summary(Loan $l, $viewer): array
    {
        $paid = (int) $l->installments()->sum('paid_amount');

        return ['id' => $l->id, 'amount' => $l->amount, 'interest_rate' => $l->interest_rate, 'term_months' => $l->term_months, 'purpose' => $l->purpose, 'status' => $l->status,
            'total_interest' => $l->total_interest, 'total_due' => $l->total_due, 'paid' => $paid, 'progress' => $l->total_due ? (int) round(100 * $paid / $l->total_due) : 0,
            'borrower' => ['membership_id' => $l->membership_id, 'name' => $l->membership->user->name, 'avatar_color' => $l->membership->user->avatar_color], 'created_at' => $l->created_at, 'disbursed_at' => $l->disbursed_at, 'is_mine' => $l->membership_id === $viewer->id];
    }
}
