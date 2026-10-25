<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanGuarantor extends Model
{
    protected $guarded = [];

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}
