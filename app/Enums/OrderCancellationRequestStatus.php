<?php

namespace App\Enums;

enum OrderCancellationRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
