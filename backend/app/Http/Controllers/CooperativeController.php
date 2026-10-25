<?php

namespace App\Http\Controllers;

use App\Models\Cooperative;
use App\Models\Membership;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CooperativeController extends Controller
{
    public function __construct(private LedgerService $ledger) {}

    public function mine(Request $r)
    {
        return response()->json($r->user()->memberships()->with('cooperative')->where('status', 'active')->get()->map(fn ($m) => [
            'id' => $m->cooperative->id, 'name' => $m->cooperative->name, 'city' => $m->cooperative->city, 'role' => $m->role->value, 'member_number' => $m->member_number, 'membership_id' => $m->id,
        ]));
    }

    public function store(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|min:3|max:80', 'description' => 'nullable|string|max:300', 'city' => 'nullable|string|max:60']);
        $coop = Cooperative::create($d + ['slug' => Str::slug($d['name']).'-'.Str::lower(Str::random(4)), 'invite_code' => strtoupper(Str::random(8)), 'settings' => Cooperative::DEFAULTS, 'created_by' => $r->user()->id]);
        $m = $this->addMember($coop, $r->user()->id, 'admin');
        $this->ledger->fundOf($coop);

        return response()->json(['id' => $coop->id, 'membership_id' => $m->id], 201);
    }

    public function join(Request $r)
    {
        $d = $r->validate(['invite_code' => 'required|string']);
        $coop = Cooperative::where('invite_code', strtoupper(trim($d['invite_code'])))->first();
        if (! $coop) {
            throw new \DomainException('Code d\'invitation invalide.');
        }
        if (Membership::where('cooperative_id', $coop->id)->where('user_id', $r->user()->id)->exists()) {
            throw new \DomainException('Vous êtes déjà membre de cette coopérative.');
        }
        $this->addMember($coop, $r->user()->id, 'member');

        return response()->json(['id' => $coop->id]);
    }

    public static function nextNumber(int $coopId): string
    {
        return 'M-'.str_pad((string) (Membership::where('cooperative_id', $coopId)->count() + 1), 4, '0', STR_PAD_LEFT);
    }

    private function addMember(Cooperative $coop, int $userId, string $role): Membership
    {
        $m = Membership::create(['cooperative_id' => $coop->id, 'user_id' => $userId, 'role' => $role, 'status' => 'active', 'member_number' => self::nextNumber($coop->id), 'joined_at' => now()]);
        $this->ledger->accountOf($m);

        return $m;
    }
}
