<?php

namespace App\Http\Controllers;

use App\Enums\LedgerType;
use App\Services\LedgerService;
use App\Services\PaymentSimulator;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(private LedgerService $ledger) {}

    public function show(Request $r)
    {
        $m = $r->attributes->get('membership');
        $a = $this->ledger->accountOf($m);

        return response()->json(['balance' => $a->balance, 'member_number' => $m->member_number, 'holder' => $m->user->name, 'entries' => $a->entries()->take(5)->get()]);
    }

    public function entries(Request $r)
    {
        $a = $this->ledger->accountOf($r->attributes->get('membership'));
        $q = $a->entries();
        if ($t = $r->query('type')) {
            $q->where('type', $t);
        }
        if ($f = $r->query('from')) {
            $q->whereDate('created_at', '>=', $f);
        }
        if ($to = $r->query('to')) {
            $q->whereDate('created_at', '<=', $to);
        }

        return response()->json($q->paginate(20));
    }

    public function deposit(Request $r, \App\Models\Cooperative $coop, PaymentSimulator $sim)
    {
        $d = $r->validate(['provider' => 'required|in:mtn,moov,celtiis', 'phone' => 'required|string|min:8|max:20', 'amount' => 'required|integer|min:500|max:2000000']);
        $p = $sim->initiate($r->attributes->get('membership'), 'deposit', $d['provider'], $d['phone'], $d['amount']);

        return response()->json($p, 202);
    }

    public function withdraw(Request $r)
    {
        $d = $r->validate(['provider' => 'required|in:mtn,moov,celtiis', 'phone' => 'required|string|min:8|max:20', 'amount' => 'required|integer|min:500']);
        $a = $this->ledger->accountOf($r->attributes->get('membership'));
        $this->ledger->debit($a, $d['amount'], LedgerType::Withdrawal, ['provider' => $d['provider'], 'phone' => $d['phone']], null, 'Retrait Mobile Money '.strtoupper($d['provider']));

        return response()->json(['balance' => $a->refresh()->balance]);
    }
}
