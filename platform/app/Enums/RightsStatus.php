<?php

namespace App\Enums;

enum RightsStatus: string
{
    case Unverified = 'unverified';
    case Verified = 'verified';
    case Rejected = 'rejected';
}
