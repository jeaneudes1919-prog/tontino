<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cycle extends Model
{
    protected $guarded = [];

    protected $casts = ['due_date' => 'date', 'paid_out_at' => 'datetime'];

    public function tontine()
    {
        return $this->belongsTo(Tontine::class);
    }

    public function beneficiary()
    {
        return $this->belongsTo(Membership::class, 'beneficiary_membership_id');
    }

    public function contributions()
    {
        return $this->hasMany(Contribution::class);
    }
}
