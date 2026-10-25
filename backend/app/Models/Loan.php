<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = ['interest_rate' => 'float', 'reviewed_at' => 'datetime', 'disbursed_at' => 'datetime'];

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(Membership::class, 'reviewed_by');
    }

    public function guarantors()
    {
        return $this->hasMany(LoanGuarantor::class);
    }

    public function installments()
    {
        return $this->hasMany(LoanInstallment::class)->orderBy('number');
    }
}
