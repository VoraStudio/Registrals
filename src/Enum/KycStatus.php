<?php

declare(strict_types=1);

namespace App\Enum;

enum KycStatus: string
{
    case UNVERIFIED = 'unverified';
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
