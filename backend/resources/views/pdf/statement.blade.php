<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;color:#0f2a26;font-size:11px}
h1{color:#052e2b;font-size:20px;margin:0}h2{color:#052e2b;font-size:13px;border-bottom:2px solid #D4AF37;padding-bottom:3px;margin-top:20px}
.head{background:#052e2b;color:#F5F1E6;padding:16px;border-radius:6px}.gold{color:#D4AF37}
table{width:100%;border-collapse:collapse;margin-top:6px}th{background:#eef3f1;text-align:left;padding:5px;font-size:10px}td{padding:5px;border-bottom:1px solid #e3ebe8}
.r{text-align:right}.c{color:#1a7f5a}.d{color:#b42318}
</style></head><body>
<div class="head"><h1>Relevé de compte</h1><p>{{ $coop->name }} — <span class="gold">{{ $m->member_number }}</span></p></div>
<p><b>{{ $m->user->name }}</b> · période du {{ $from->format('d/m/Y') }} au {{ $to->format('d/m/Y') }}<br>Solde actuel : <b>{{ number_format($balance, 0, ',', ' ') }} FCFA</b></p>
<h2>Mouvements</h2>
<table><tr><th>Date</th><th>Libellé</th><th class="r">Montant</th><th class="r">Solde</th></tr>
@forelse ($entries as $e)<tr><td>{{ \Illuminate\Support\Carbon::parse($e->created_at)->format('d/m/Y') }}</td><td>{{ $e->description ?? $e->type }}</td><td class="r {{ $e->direction === 'credit' ? 'c' : 'd' }}">{{ $e->direction === 'credit' ? '+' : '-' }}{{ number_format($e->amount, 0, ',', ' ') }}</td><td class="r">{{ number_format($e->balance_after, 0, ',', ' ') }}</td></tr>
@empty<tr><td colspan="4">Aucun mouvement sur la période.</td></tr>@endforelse</table>
<h2>Cotisations</h2>
<table><tr><th>Tontine</th><th>Cycle</th><th>Échéance</th><th class="r">Montant</th><th>Statut</th></tr>
@forelse ($contributions as $c)<tr><td>{{ $c->cycle->tontine->name }}</td><td>{{ $c->cycle->number }}</td><td>{{ $c->due_date->format('d/m/Y') }}</td><td class="r">{{ number_format($c->amount + $c->penalty_amount, 0, ',', ' ') }}</td><td>{{ ['pending' => 'En attente', 'paid' => 'Payée', 'late' => 'En retard', 'waived' => 'Remise'][$c->status] }}</td></tr>
@empty<tr><td colspan="5">Aucune cotisation.</td></tr>@endforelse</table>
<h2>Prêts</h2>
<table><tr><th>Montant</th><th>Durée</th><th>Total dû</th><th>Statut</th></tr>
@forelse ($loans as $l)<tr><td>{{ number_format($l->amount, 0, ',', ' ') }}</td><td>{{ $l->term_months }} mois</td><td>{{ number_format($l->total_due, 0, ',', ' ') }}</td><td>{{ $l->status }}</td></tr>@empty<tr><td colspan="4">Aucun prêt.</td></tr>@endforelse</table>
</body></html>
