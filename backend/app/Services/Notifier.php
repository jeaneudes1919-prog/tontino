<?php

namespace App\Services;

use App\Models\Membership;
use App\Notifications\TontinoNotice;

class Notifier
{
    /** @param iterable<Membership> $memberships */
    public function send(iterable $memberships, string $title, string $body, string $type = 'info'): void
    {
        foreach (collect($memberships)->filter()->unique('id') as $m) {
            $m->loadMissing('user');
            $m->user?->notify(new TontinoNotice($title, $body, $type, $m->cooperative_id));
        }
    }

    public function managers(int $cooperativeId)
    {
        return Membership::with('user')->where('cooperative_id', $cooperativeId)->whereIn('role', ['admin', 'treasurer'])->where('status', 'active')->get();
    }
}
