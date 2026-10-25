<?php

namespace App\Services;

use App\Models\Contribution;
use App\Models\Cycle;
use App\Models\Membership;
use App\Models\Tontine;
use App\Models\TontineMember;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TontineService
{
    public function __construct(private Notifier $notifier) {}

    public function addMembers(Tontine $t, array $membershipIds): void
    {
        if ($t->status !== 'draft') {
            throw new \DomainException('Les membres ne peuvent être modifiés que pendant la préparation de la tontine.');
        }
        foreach ($membershipIds as $id) {
            if ($t->tontineMembers()->count() >= $t->max_members) {
                throw new \DomainException('Le nombre maximum de membres est atteint ('.$t->max_members.').');
            }
            $m = Membership::where('cooperative_id', $t->cooperative_id)->where('status', 'active')->findOrFail($id);
            TontineMember::firstOrCreate(['tontine_id' => $t->id, 'membership_id' => $m->id]);
        }
    }

    public function removeMember(Tontine $t, int $membershipId): void
    {
        if ($t->status !== 'draft') {
            throw new \DomainException('Les membres ne peuvent être modifiés que pendant la préparation de la tontine.');
        }
        $t->tontineMembers()->where('membership_id', $membershipId)->delete();
    }

    /** Tirage au sort sécurisé (Fisher-Yates avec random_int) puis génération des cycles et des cotisations. */
    public function draw(Tontine $t): Collection
    {
        if ($t->drawn_at) {
            throw new \DomainException('Le tirage au sort a déjà été effectué.');
        }
        $members = $t->tontineMembers()->get();
        if ($members->count() < 2) {
            throw new \DomainException('Il faut au moins 2 membres pour effectuer le tirage.');
        }

        return DB::transaction(function () use ($t, $members) {
            $ids = $members->pluck('membership_id')->all();
            for ($i = count($ids) - 1; $i > 0; $i--) {
                $j = random_int(0, $i);
                [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
            }
            $rotative = $t->type === 'rotative';
            $n = $rotative ? count($ids) : 12;
            foreach ($ids as $pos => $mid) {
                $t->tontineMembers()->where('membership_id', $mid)->update(['position' => $pos + 1]);
            }
            for ($k = 1; $k <= $n; $k++) {
                $due = $t->start_date->copy();
                $due = match ($t->frequency) {
                    'weekly' => $due->addWeeks($k - 1),
                    'biweekly' => $due->addWeeks(($k - 1) * 2),
                    default => $due->addMonthsNoOverflow($k - 1),
                };
                $cycle = Cycle::create([
                    'tontine_id' => $t->id, 'number' => $k, 'due_date' => $due, 'status' => 'upcoming',
                    'beneficiary_membership_id' => $rotative ? $ids[$k - 1] : null,
                    'expected_amount' => $t->contribution_amount * count($ids),
                ]);
                foreach ($ids as $mid) {
                    Contribution::create(['cycle_id' => $cycle->id, 'membership_id' => $mid, 'amount' => $t->contribution_amount, 'due_date' => $due, 'status' => 'pending']);
                }
            }
            $t->update(['drawn_at' => now(), 'draw_seed' => bin2hex(random_bytes(8))]);

            return $t->tontineMembers()->with('membership.user')->orderBy('position')->get();
        });
    }

    public function activate(Tontine $t): void
    {
        if (! $t->drawn_at) {
            throw new \DomainException('Effectuez le tirage au sort avant d\'activer la tontine.');
        }
        if ($t->status !== 'draft') {
            throw new \DomainException('Cette tontine est déjà active.');
        }
        $t->update(['status' => 'active']);
        $this->notifier->send(Membership::with('user')->whereIn('id', $t->tontineMembers()->pluck('membership_id'))->get(), 'Tontine lancée : '.$t->name, 'La première cotisation est attendue le '.$t->start_date->format('d/m/Y').'.', 'tontine');
    }
}
