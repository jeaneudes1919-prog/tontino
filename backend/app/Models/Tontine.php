<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class Tontine extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = ['start_date' => 'date', 'drawn_at' => 'datetime'];

    public function cooperative()
    {
        return $this->belongsTo(Cooperative::class);
    }

    public function tontineMembers()
    {
        return $this->hasMany(TontineMember::class);
    }

    public function cycles()
    {
        return $this->hasMany(Cycle::class)->orderBy('number');
    }
}
