<?php

namespace App\Models;

use App\Enums\MembershipRole;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = ['role' => MembershipRole::class, 'joined_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cooperative()
    {
        return $this->belongsTo(Cooperative::class);
    }

    public function isManager(): bool
    {
        return in_array($this->role, [MembershipRole::Admin, MembershipRole::Treasurer], true);
    }

    public function isAdmin(): bool
    {
        return $this->role === MembershipRole::Admin;
    }
}
