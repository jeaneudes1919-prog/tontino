<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TontineMember extends Model
{
    protected $guarded = [];

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    public function tontine()
    {
        return $this->belongsTo(Tontine::class);
    }
}
