<?php

namespace Database\Seeders;

use App\Enums\LedgerType;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Contribution;
use App\Models\Cooperative;
use App\Models\Loan;
use App\Models\LoanGuarantor;
use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\Membership;
use App\Models\Tontine;
use App\Models\User;
use App\Notifications\TontinoNotice;
use App\Services\ContributionService;
use App\Services\LedgerService;
use App\Services\LoanService;
use App\Services\PayoutService;
use App\Services\TontineService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        AuditLog::$enabled = false;
        mt_srand(7);
        $ledger = app(LedgerService::class);
        $tontines = app(TontineService::class);
        $contribs = app(ContributionService::class);
        $payouts = app(PayoutService::class);
        $loans = app(LoanService::class);

        $names = ['Administratrice Tontino', 'Trésorier Tontino', 'Membre Démo', 'Aïcha Bio', 'Sènami Dossou', 'Fatou Diallo', 'Rodrigue Hounsa', 'Chantal Ahouansou', 'Félicité Gbaguidi', 'Marcel Zinsou', 'Rita Adjovi', 'Josiane Medji',
            'Nadia Fassinou', 'Prisca Tchibozo', 'Carine Adjaho', 'Yvette Kiki', 'Armelle Sossou', 'Gisèle Dansou', 'Mireille Akpovi', 'Dorcas Houngbo', 'Estelle Agbo', 'Larissa Tokpa', 'Reine Kpossou', 'Odette Vignon', 'Béatrice Amoussou'];
        $emails = ['admin@tontino.dev', 'tresor@tontino.dev', 'membre@tontino.dev'];
        $colors = ['#D4AF37', '#34D399', '#60A5FA', '#F472B6', '#FBBF24', '#A78BFA', '#FB7185'];
        $users = collect($names)->map(fn ($n, $i) => User::create(['name' => $n, 'email' => $emails[$i] ?? 'membre'.($i + 1).'@tontino.dev', 'phone' => '+229 01 9'.rand(1000000, 9999999), 'password' => 'Tontino2026!', 'avatar_color' => $colors[$i % 7]]));

        $coop = Cooperative::create(['name' => 'Les Lionnes de Cotonou', 'slug' => 'les-lionnes-de-cotonou', 'description' => 'Coopérative d\'épargne et de tontines de femmes entrepreneures de Cotonou.', 'city' => 'Cotonou',
            'invite_code' => 'LIONNES26', 'settings' => Cooperative::DEFAULTS, 'created_by' => $users[0]->id]);
        $ms = $users->map(fn ($u, $i) => Membership::create(['cooperative_id' => $coop->id, 'user_id' => $u->id, 'role' => $i === 0 ? 'admin' : ($i === 1 ? 'treasurer' : 'member'), 'status' => 'active',
            'member_number' => 'M-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT), 'joined_at' => now()->subMonths(8)]));
        $fund = $ledger->fundOf($coop);
        $ledger->credit($fund, 5000000, LedgerType::Adjustment, [], null, 'Capital initial de la coopérative');
        foreach ($ms as $m) {
            $ledger->credit($ledger->accountOf($m), rand(4, 20) * 10000, LedgerType::Deposit, ['provider' => ['mtn', 'moov', 'celtiis'][rand(0, 2)]], null, 'Dépôt Mobile Money');
        }
        $fill = fn (Membership $m, int $amount) => $ledger->credit($ledger->accountOf($m), $amount, LedgerType::Deposit, [], null, 'Dépôt Mobile Money');

        // Tontine A : hebdomadaire, rotative, 12 membres, 6 cycles échus
        $a = Tontine::create(['cooperative_id' => $coop->id, 'name' => 'Tontine Hebdo 10 000', 'description' => 'Cotisation de 10 000 FCFA chaque semaine, une bénéficiaire par semaine.', 'type' => 'rotative', 'contribution_amount' => 10000, 'frequency' => 'weekly', 'start_date' => today()->subWeeks(5), 'max_members' => 12, 'penalty_percent' => 5, 'status' => 'draft']);
        $tontines->addMembers($a, $ms->take(12)->pluck('id')->all());
        $tontines->draw($a);
        $tontines->activate($a);
        $this->settle($a, 6, $fill, $contribs, $payouts, 2);
        // Tontine B : épargne mensuelle, 15 membres
        $b = Tontine::create(['cooperative_id' => $coop->id, 'name' => 'Épargne Mensuelle 25 000', 'description' => 'Épargne collective mensuelle partagée en fin d\'année.', 'type' => 'savings', 'contribution_amount' => 25000, 'frequency' => 'monthly', 'start_date' => today()->subMonths(3)->addDays(2), 'max_members' => 20, 'penalty_percent' => 5, 'status' => 'draft']);
        $tontines->addMembers($b, $ms->take(15)->pluck('id')->all());
        $tontines->draw($b);
        $tontines->activate($b);
        $this->settle($b, 3, $fill, $contribs, $payouts, 0);
        // Tontine C : en préparation, prête pour le tirage (démo)
        $c = Tontine::create(['cooperative_id' => $coop->id, 'name' => 'Tontine Fêtes de fin d\'année', 'description' => 'Pour préparer sereinement les fêtes : tirage au sort à effectuer.', 'type' => 'rotative', 'contribution_amount' => 20000, 'frequency' => 'monthly', 'start_date' => today()->addMonth(), 'max_members' => 12, 'penalty_percent' => 5, 'status' => 'draft']);
        $tontines->addMembers($c, $ms->slice(2, 10)->pluck('id')->all());

        $this->loans($coop, $ms, $ledger, $loans, $fill);

        foreach ([['Bienvenue aux nouvelles membres', 'Nous accueillons cinq nouvelles membres ce mois-ci. Bienvenue dans la famille Lionnes !', true], ['Assemblée générale annuelle', 'L\'assemblée générale aura lieu dans trois semaines. Votre présence est importante.', false],
            ['Rappel : cotisations hebdomadaires', 'Merci de régler vos cotisations avant l\'échéance pour éviter les pénalités de 5 %.', false], ['Nouveau : paiement Mobile Money', 'Vous pouvez désormais payer vos cotisations directement avec MTN, Moov ou Celtiis.', false],
            ['Résultats du trimestre', 'La caisse a progressé de 12 % ce trimestre grâce à vos remboursements ponctuels.', false], ['Formation en gestion', 'Une formation gratuite en gestion d\'activité se tiendra le mois prochain.', false]] as $i => [$t, $body, $pin]) {
            Announcement::create(['cooperative_id' => $coop->id, 'author_id' => $users[$i % 2]->id, 'title' => $t, 'body' => $body, 'pinned' => $pin, 'published_at' => now()->subDays($i * 6)]);
        }
        foreach (range(1, 7) as $i) {
            $past = $i <= 5;
            $mt = Meeting::create(['cooperative_id' => $coop->id, 'title' => $past ? 'Réunion mensuelle n°'.(6 - $i) : ($i === 6 ? 'Assemblée générale' : 'Réunion mensuelle'), 'starts_at' => $past ? now()->subWeeks($i * 3)->setTime(16, 0) : now()->addWeeks($i - 5)->setTime(16, 0),
                'location' => 'Maison des associations, Cotonou', 'agenda' => 'Point sur la caisse, tontines en cours, demandes de prêt, questions diverses.', 'minutes' => $past ? 'Compte rendu validé par le bureau.' : null, 'status' => $past ? 'done' : 'scheduled']);
            if ($past) {
                foreach ($ms as $m) {
                    MeetingAttendance::create(['meeting_id' => $mt->id, 'membership_id' => $m->id, 'status' => ['present', 'present', 'present', 'absent', 'excused'][rand(0, 4)]]);
                }
            }
        }
        foreach ([0, 1, 2] as $i) {
            $users[$i]->notify(new TontinoNotice('Bienvenue sur Tontino', 'Votre espace de coopérative est prêt.', 'info', $coop->id));
            $users[$i]->notify(new TontinoNotice('Cotisation à venir', 'Votre prochaine cotisation hebdomadaire approche.', 'contribution', $coop->id));
        }
        $users[1]->notify(new TontinoNotice('Nouvelle demande de prêt', 'Une demande de prêt attend votre validation.', 'loan', $coop->id));
        AuditLog::$enabled = true;
    }

    private function settle(Tontine $t, int $cycles, \Closure $fill, ContributionService $contribs, PayoutService $payouts, int $payoutUpTo): void
    {
        foreach ($t->cycles()->where('number', '<=', $cycles)->get() as $cycle) {
            $cycle->update(['status' => 'open']);
            foreach ($cycle->contributions()->with('membership')->get() as $c) {
                $r = rand(1, 100);
                $isCurrent = $cycle->due_date->gte(today());
                if ($r <= 85 || $isCurrent && $r <= 60) {
                    $fill($c->membership, $c->amount);
                    $contribs->payFromWallet($c, $cycle->due_date->copy()->setTime(rand(8, 17), rand(0, 59)));
                } elseif (! $isCurrent) {
                    $contribs->markLate($c);
                    if ($r <= 97) {
                        $c->refresh();
                        $fill($c->membership, $c->total());
                        $contribs->payFromWallet($c, $cycle->due_date->copy()->addDays(rand(2, 5)));
                    }
                }
            }
            $cycle->refresh();
            if ($cycle->number <= $payoutUpTo && $cycle->status === 'ready') {
                $payouts->payout($cycle);
            }
        }
    }

    private function loans(Cooperative $coop, $ms, LedgerService $ledger, LoanService $svc, \Closure $fill): void
    {
        $mk = fn (int $i, int $amount, int $months, string $purpose, string $status, array $g = [], array $gs = []) => tap(Loan::create([
            'cooperative_id' => $coop->id, 'membership_id' => $ms[$i]->id, 'amount' => $amount, 'interest_rate' => 12, 'term_months' => $months, 'purpose' => $purpose, 'status' => $status,
            'total_interest' => 0, 'total_due' => $amount]), function ($loan) use ($ms, $g, $gs) {
                foreach ($g as $k => $gi) {
                    LoanGuarantor::create(['loan_id' => $loan->id, 'membership_id' => $ms[$gi]->id, 'status' => $gs[$k] ?? 'accepted']);
                }
            });
        $mk(4, 120000, 6, 'Achat de stock pour ma boutique de tissus', 'requested', [5, 6], ['pending', 'accepted']);
        $mk(5, 80000, 4, 'Équipement pour mon salon de coiffure', 'requested', [6, 7], ['accepted', 'accepted']);
        $mk(6, 150000, 8, 'Extension de mon atelier de couture', 'approved', [7, 8]);
        $mk(7, 500000, 12, 'Création d\'une activité de restauration', 'rejected', [8])->update(['rejection_reason' => 'Montant supérieur à la capacité de remboursement estimée.', 'reviewed_by' => $ms[1]->id, 'reviewed_at' => now()->subDays(9)]);
        foreach ([[8, 200000, 6, 2, 'partial'], [9, 100000, 4, 6, 'repaid'], [10, 90000, 6, 8, 'defaulted']] as [$i, $amount, $months, $ago, $kind]) {
            $loan = $mk($i, $amount, $months, ['Fonds de roulement pour mon commerce', 'Achat d\'une machine à coudre', 'Soutien familial exceptionnel'][$i - 8], 'approved', [$i + 1, $i + 2]);
            $loan->update(['reviewed_by' => $ms[1]->id, 'reviewed_at' => now()->subMonths($ago)]);
            $svc->disburse($loan);
            $loan->refresh();
            foreach ($loan->installments as $inst) {
                $inst->update(['due_date' => $inst->due_date->copy()->subMonths($ago)]);
            }
            $loan->update(['disbursed_at' => now()->subMonths($ago)]);
            if ($kind === 'partial') {
                $fill($ms[$i], 100000);
                $svc->repay($loan, 100000, $ms[$i]);
            } elseif ($kind === 'repaid') {
                $fill($ms[$i], $loan->total_due);
                $svc->repay($loan, $loan->total_due, $ms[$i]);
            }
        }
        $svc->flagLate();
    }
}
