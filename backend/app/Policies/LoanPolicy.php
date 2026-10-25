<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\Membership;

class LoanPolicy
{
    public function view(Membership $m, Loan $loan): bool
    {
        return $m->isManager() || $loan->membership_id === $m->id || $loan->guarantors()->where('membership_id', $m->id)->exists();
    }

    public function review(Membership $m, Loan $loan): bool
    {
        return $m->isManager() && $loan->membership_id !== $m->id;
    }
}
