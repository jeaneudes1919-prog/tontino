<?php

namespace App\Http\Middleware;

use App\Models\Contribution;
use App\Models\Cooperative;
use App\Models\Cycle;
use App\Models\Membership;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCooperative
{
    public function handle(Request $request, Closure $next): Response
    {
        $coop = $request->route('coop');
        $coop = $coop instanceof Cooperative ? $coop : Cooperative::find($coop);
        abort_unless($coop, 404, 'Coopérative introuvable.');
        $membership = Membership::with('user')->where('cooperative_id', $coop->id)->where('user_id', $request->user()->id)->where('status', 'active')->first();
        abort_unless($membership, 403, 'Vous n\'êtes pas membre de cette coopérative.');
        foreach ($request->route()->parameters() as $name => $model) {
            if ($name === 'coop' || ! is_object($model)) {
                continue;
            }
            $owner = match (true) {
                $model instanceof Contribution => $model->cycle->tontine->cooperative_id,
                $model instanceof Cycle => $model->tontine->cooperative_id,
                default => $model->cooperative_id ?? null,
            };
            abort_unless($owner === null || $owner === $coop->id, 404, 'Ressource introuvable.');
        }
        $membership->setRelation('cooperative', $coop);
        $request->attributes->set('coop', $coop);
        $request->attributes->set('membership', $membership);

        return $next($request);
    }
}
