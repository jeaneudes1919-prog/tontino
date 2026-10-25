<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanInstallment extends Model
{
    protected $guarded = [];

    protected $casts = ['due_date' => 'date', 'paid_at' => 'datetime'];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function remaining(): int
    {
        return max(0, $this->principal + $this->interest - $this->paid_amount);
    }
}
