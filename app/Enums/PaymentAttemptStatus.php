<?php

namespace App\Enums;

enum PaymentAttemptStatus: string
{
    case Pending = 'pending';
    case RequiresAction = 'requires_action';
    case Processing = 'processing';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Paid,
            self::Failed,
            self::Cancelled,
            self::Expired,
        ], true);
    }
}
