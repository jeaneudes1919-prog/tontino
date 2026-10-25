<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCoopRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $m = $request->attributes->get('membership');
        abort_unless($m && in_array($m->role->value, $roles, true), 403, 'Action réservée aux responsables de la coopérative.');

        return $next($request);
    }
}
