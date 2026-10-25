<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $guarded = [];

    protected $casts = ['completed_at' => 'datetime'];

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }
}
