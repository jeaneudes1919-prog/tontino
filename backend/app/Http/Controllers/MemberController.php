<?php

namespace App\Http\Controllers;

use App\Models\Contribution;
use App\Models\Cooperative;
use App\Models\Loan;
use App\Models\Membership;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MemberController extends Controller
{
    public function index(Request $r, Cooperative $coop, LedgerService $ledger)
    {
        $me = $r->attributes->get('membership');
        $q = Membership::with('user')->where('cooperative_id', $coop->id)->orderBy('member_number');
        if ($role = $r->query('role')) {
            $q->where('role', $role);
        }
        if ($status = $r->query('status')) {
            $q->where('status', $status);
        }
        if ($term = $r->query('q')) {
            $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%$term%")->orWhere('email', 'like', "%$term%"));
        }

        return response()->json($q->get()->map(fn ($m) => $this->out($m, $me->isManager() || $m->id === $me->id ? $ledger->accountOf($m)->balance : null)));
    }

    public function show(Request $r, Cooperative $coop, Membership $membership, LedgerService $ledger)
    {
        $me = $r->attributes->get('membership');
        $full = $me->isManager() || $me->id === $membership->id;
        $out = $this->out($membership, $full ? $ledger->accountOf($membership)->balance : null);
        if ($full) {
            $out['contributions_paid'] = (int) Contribution::where('membership_id', $membership->id)->where('status', 'paid')->sum('amount');
            $out['late_contributions'] = Contribution::where('membership_id', $membership->id)->where('status', 'late')->count();
            $out['loans'] = Loan::where('membership_id', $membership->id)->get(['id', 'amount', 'status', 'total_due']);
        }

        return response()->json($out);
    }

    public function store(Request $r, Cooperative $coop, LedgerService $ledger)
    {
        $d = $r->validate(['name' => 'required|string|min:2|max:80', 'email' => 'required|email', 'phone' => 'nullable|string|max:20', 'role' => 'required|in:admin,treasurer,member']);
        $temp = Str::random(10);
        $user = User::firstOrCreate(['email' => $d['email']], ['name' => $d['name'], 'phone' => $d['phone'] ?? null, 'password' => $temp, 'avatar_color' => '#D4AF37']);
        if (Membership::where('cooperative_id', $coop->id)->where('user_id', $user->id)->exists()) {
            throw new \DomainException('Cette personne est déjà membre.');
        }
        $m = Membership::create(['cooperative_id' => $coop->id, 'user_id' => $user->id, 'role' => $d['role'], 'status' => 'active', 'member_number' => CooperativeController::nextNumber($coop->id), 'joined_at' => now()]);
        $ledger->accountOf($m);

        return response()->json(['membership' => $this->out($m->load('user')), 'temporary_password' => $user->wasRecentlyCreated ? $temp : null], 201);
    }

    public function update(Request $r, Cooperative $coop, Membership $membership)
    {
        $d = $r->validate(['role' => 'sometimes|in:admin,treasurer,member', 'status' => 'sometimes|in:active,suspended']);
        if ($membership->id === $r->attributes->get('membership')->id && (($d['role'] ?? 'admin') !== 'admin' || ($d['status'] ?? 'active') !== 'active')) {
            throw new \DomainException('Vous ne pouvez pas modifier votre propre rôle ou statut.');
        }
        $membership->update($d);

        return response()->json($this->out($membership->load('user')));
    }

    private function out(Membership $m, ?int $balance = null): array
    {
        return ['id' => $m->id, 'member_number' => $m->member_number, 'role' => $m->role->value, 'status' => $m->status, 'joined_at' => $m->joined_at, 'balance' => $balance,
            'user' => ['id' => $m->user->id, 'name' => $m->user->name, 'email' => $m->user->email, 'phone' => $m->user->phone, 'avatar_color' => $m->user->avatar_color]];
    }
}
