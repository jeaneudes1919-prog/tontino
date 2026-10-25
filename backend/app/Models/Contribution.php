<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class Contribution extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = ['due_date' => 'date', 'paid_at' => 'datetime'];

    public function cycle()
    {
        return $this->belongsTo(Cycle::class);
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    public function total(): int
    {
        return $this->amount + $this->penalty_amount;
    }
}
