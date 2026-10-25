<?php

namespace App\Enums;

enum MembershipRole: string
{
    case Admin = 'admin';
    case Treasurer = 'treasurer';
    case Member = 'member';
}
