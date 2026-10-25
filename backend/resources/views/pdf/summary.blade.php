<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;color:#0f2a26;font-size:11px}
h1{color:#052e2b;font-size:20px;margin:0}h2{color:#052e2b;font-size:13px;border-bottom:2px solid #D4AF37;padding-bottom:3px;margin-top:20px}
.head{background:#052e2b;color:#F5F1E6;padding:16px;border-radius:6px}.gold{color:#D4AF37}
table{width:100%;border-collapse:collapse;margin-top:6px}th{background:#eef3f1;text-align:left;padding:6px;font-size:10px}td{padding:6px;border-bottom:1px solid #e3ebe8}.r{text-align:right}
</style></head><body>
<div class="head"><h1>Synthèse mensuelle</h1><p>{{ $coop->name }} — <span class="gold">{{ ucfirst($month->translatedFormat('F Y')) }}</span></p></div>
<h2>Indicateurs</h2>
<table>
<tr><td>Solde de la caisse</td><td class="r"><b>{{ number_format($fund, 0, ',', ' ') }} FCFA</b></td></tr>
<tr><td>Membres actifs</td><td class="r">{{ $members }}</td></tr>
<tr><td>Cotisations collectées</td><td class="r">{{ number_format($collected, 0, ',', ' ') }} FCFA</td></tr>
<tr><td>Cotisations en retard (en cours)</td><td class="r">{{ $late }}</td></tr>
<tr><td>Cagnottes versées</td><td class="r">{{ number_format($payouts, 0, ',', ' ') }} FCFA</td></tr>
<tr><td>Prêts décaissés</td><td class="r">{{ number_format($disbursed, 0, ',', ' ') }} FCFA</td></tr>
<tr><td>Remboursements et intérêts reçus</td><td class="r">{{ number_format($repaid, 0, ',', ' ') }} FCFA</td></tr>
</table>
<h2>Prêts en cours</h2>
<table><tr><th>Emprunteur</th><th class="r">Montant</th><th class="r">Total dû</th><th>Statut</th></tr>
@forelse ($loans as $l)<tr><td>{{ $l->membership->user->name }}</td><td class="r">{{ number_format($l->amount, 0, ',', ' ') }}</td><td class="r">{{ number_format($l->total_due, 0, ',', ' ') }}</td><td>{{ $l->status === 'defaulted' ? 'En défaut' : 'En cours' }}</td></tr>@empty<tr><td colspan="4">Aucun prêt en cours.</td></tr>@endforelse</table>
</body></html>
