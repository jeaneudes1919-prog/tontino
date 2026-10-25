<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = ['starts_at' => 'datetime'];

    public function attendances()
    {
        return $this->hasMany(MeetingAttendance::class);
    }
}
