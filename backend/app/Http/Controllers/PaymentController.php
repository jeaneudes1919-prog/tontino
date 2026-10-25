<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $r)
    {
        return response()->json(Payment::where('membership_id', $r->attributes->get('membership')->id)->latest()->take(50)->get());
    }

    public function show(Request $r, \App\Models\Cooperative $coop, string $reference)
    {
        $m = $r->attributes->get('membership');
        $p = Payment::where('cooperative_id', $coop->id)->where('reference', $reference)->firstOrFail();
        abort_unless($p->membership_id === $m->id || $m->isManager(), 403);

        return response()->json($p);
    }
}
