<?php

namespace App\Http\Controllers;

use App\Models\Contribution;
use App\Models\Cooperative;
use App\Services\ContributionService;
use App\Services\PaymentSimulator;
use Illuminate\Http\Request;

class ContributionController extends Controller
{
    public function index(Request $r, Cooperative $coop)
    {
        $m = $r->attributes->get('membership');
        $q = Contribution::with(['cycle.tontine', 'membership.user'])->whereHas('cycle.tontine', fn ($t) => $t->where('cooperative_id', $coop->id))->orderBy('due_date');
        if (! $m->isManager() || $r->boolean('mine')) {
            $q->where('membership_id', $m->id);
        }
        if ($s = $r->query('status')) {
            $q->where('status', $s);
        }
        if ($t = $r->query('tontine_id')) {
            $q->whereHas('cycle', fn ($c) => $c->where('tontine_id', $t));
        }

        return response()->json($q->paginate(30)->through(fn ($c) => [
            'id' => $c->id, 'tontine' => $c->cycle->tontine->name, 'tontine_id' => $c->cycle->tontine_id, 'cycle' => $c->cycle->number, 'member' => $c->membership->user->name, 'membership_id' => $c->membership_id,
            'amount' => $c->amount, 'penalty_amount' => $c->penalty_amount, 'total' => $c->total(), 'due_date' => $c->due_date->toDateString(), 'status' => $c->status, 'paid_at' => $c->paid_at]));
    }

    private function mineActive(Request $r, Contribution $c): void
    {
        abort_unless($c->membership_id === $r->attributes->get('membership')->id, 403, 'Cette cotisation ne vous concerne pas.');
        if (! in_array($c->status, ['pending', 'late'], true)) {
            throw new \DomainException('Cette cotisation est déjà réglée.');
        }
        if ($c->cycle->tontine->status !== 'active') {
            throw new \DomainException('La tontine n\'est pas active.');
        }
    }

    public function pay(Request $r, Cooperative $coop, Contribution $contribution, PaymentSimulator $sim)
    {
        $this->mineActive($r, $contribution);
        $d = $r->validate(['provider' => 'required|in:mtn,moov,celtiis', 'phone' => 'required|string|min:8|max:20']);
        $p = $sim->initiate($r->attributes->get('membership'), 'contribution', $d['provider'], $d['phone'], $contribution->total(), $contribution);

        return response()->json($p, 202);
    }

    public function payFromWallet(Request $r, Cooperative $coop, Contribution $contribution, ContributionService $service)
    {
        $this->mineActive($r, $contribution);

        return response()->json($service->payFromWallet($contribution));
    }

    public function waive(Request $r, Cooperative $coop, Contribution $contribution, ContributionService $service)
    {
        $r->validate(['reason' => 'required|string|max:200']);
        if (! in_array($contribution->status, ['pending', 'late'], true)) {
            throw new \DomainException('Seules les cotisations en attente ou en retard peuvent être remises.');
        }
        $service->waive($contribution);

        return response()->json($contribution->refresh());
    }
}
