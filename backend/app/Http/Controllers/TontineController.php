<?php

namespace App\Http\Controllers;

use App\Models\Contribution;
use App\Models\Cooperative;
use App\Models\Cycle;
use App\Models\Tontine;
use App\Services\LedgerService;
use App\Services\PayoutService;
use App\Services\TontineService;
use Illuminate\Http\Request;

class TontineController extends Controller
{
    public function __construct(private TontineService $service, private LedgerService $ledger) {}

    public function index(Request $r, Cooperative $coop)
    {
        $q = Tontine::where('cooperative_id', $coop->id)->withCount('tontineMembers')->latest();
        if ($s = $r->query('status')) {
            $q->where('status', $s);
        }

        return response()->json($q->get());
    }

    public function store(Request $r, Cooperative $coop)
    {
        $d = $r->validate(['name' => 'required|string|min:3|max:80', 'description' => 'nullable|string|max:300', 'type' => 'required|in:rotative,savings', 'contribution_amount' => 'required|integer|min:500',
            'frequency' => 'required|in:weekly,biweekly,monthly', 'start_date' => 'required|date|after_or_equal:today', 'max_members' => 'required|integer|min:2|max:60', 'penalty_percent' => 'nullable|integer|min:0|max:50']);
        $t = Tontine::create($d + ['cooperative_id' => $coop->id, 'penalty_percent' => $d['penalty_percent'] ?? $coop->setting('penalty_percent'), 'status' => 'draft']);

        return response()->json($t, 201);
    }

    public function show(Request $r, Cooperative $coop, Tontine $tontine)
    {
        $m = $r->attributes->get('membership');
        $cycles = $tontine->cycles()->with('beneficiary.user')->get()->map(fn ($c) => [
            'id' => $c->id, 'number' => $c->number, 'due_date' => $c->due_date->toDateString(), 'status' => $c->status, 'expected_amount' => $c->expected_amount, 'collected_amount' => $c->collected_amount,
            'beneficiary' => $c->beneficiary ? ['id' => $c->beneficiary->id, 'name' => $c->beneficiary->user->name, 'avatar_color' => $c->beneficiary->user->avatar_color] : null,
        ]);

        return response()->json([
            'tontine' => $tontine,
            'members' => $tontine->tontineMembers()->with('membership.user')->orderBy('position')->get()->map(fn ($tm) => ['membership_id' => $tm->membership_id, 'position' => $tm->position, 'name' => $tm->membership->user->name, 'avatar_color' => $tm->membership->user->avatar_color, 'member_number' => $tm->membership->member_number]),
            'cycles' => $cycles,
            'my_contributions' => Contribution::where('membership_id', $m->id)->whereHas('cycle', fn ($q) => $q->where('tontine_id', $tontine->id))->with('cycle:id,number')->get(),
            'pot_balance' => $this->ledger->potOf($tontine)->balance,
        ]);
    }

    public function update(Request $r, Cooperative $coop, Tontine $tontine)
    {
        abort_unless($tontine->status === 'draft', 422, 'Seule une tontine en préparation peut être modifiée.');
        $d = $r->validate(['name' => 'sometimes|string|min:3|max:80', 'description' => 'nullable|string|max:300', 'contribution_amount' => 'sometimes|integer|min:500', 'frequency' => 'sometimes|in:weekly,biweekly,monthly', 'start_date' => 'sometimes|date', 'max_members' => 'sometimes|integer|min:2|max:60']);
        $tontine->update($d);

        return response()->json($tontine);
    }

    public function addMembers(Request $r, Cooperative $coop, Tontine $tontine)
    {
        $d = $r->validate(['membership_ids' => 'required|array|min:1', 'membership_ids.*' => 'integer']);
        $this->service->addMembers($tontine, $d['membership_ids']);

        return response()->json(['members' => $tontine->tontineMembers()->count()]);
    }

    public function removeMember(Request $r, Cooperative $coop, Tontine $tontine, int $membershipId)
    {
        $this->service->removeMember($tontine, $membershipId);

        return response()->json(['ok' => true]);
    }

    public function draw(Cooperative $coop, Tontine $tontine)
    {
        $order = $this->service->draw($tontine);

        return response()->json(['order' => $order->map(fn ($tm) => ['position' => $tm->position, 'membership_id' => $tm->membership_id, 'name' => $tm->membership->user->name, 'avatar_color' => $tm->membership->user->avatar_color])]);
    }

    public function activate(Cooperative $coop, Tontine $tontine)
    {
        $this->service->activate($tontine);

        return response()->json($tontine->refresh());
    }

    public function cycle(Cooperative $coop, Cycle $cycle)
    {
        $cycle->load('tontine', 'beneficiary.user');

        return response()->json(['cycle' => $cycle, 'contributions' => $cycle->contributions()->with('membership.user')->get()->map(fn ($c) => [
            'id' => $c->id, 'membership_id' => $c->membership_id, 'name' => $c->membership->user->name, 'amount' => $c->amount, 'penalty_amount' => $c->penalty_amount, 'status' => $c->status, 'paid_at' => $c->paid_at, 'due_date' => $c->due_date->toDateString()])]);
    }

    public function payout(Request $r, Cooperative $coop, Cycle $cycle, PayoutService $payouts)
    {
        $d = $r->validate(['force' => 'nullable|boolean', 'reason' => 'nullable|string|max:200']);

        return response()->json($payouts->payout($cycle, (bool) ($d['force'] ?? false), $d['reason'] ?? null));
    }
}
