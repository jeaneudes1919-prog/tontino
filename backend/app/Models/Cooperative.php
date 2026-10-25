<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cooperative extends Model
{
    protected $guarded = [];

    protected $casts = ['settings' => 'array'];

    public const DEFAULTS = ['default_interest_rate' => 12, 'penalty_percent' => 5, 'loan_max_multiplier' => 3, 'loan_min_amount' => 20000];

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }

    public function tontines()
    {
        return $this->hasMany(Tontine::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function setting(string $key)
    {
        return $this->settings[$key] ?? self::DEFAULTS[$key] ?? null;
    }
}
