<?php

namespace App\Enums;

enum PaymentMethodType: string
{
    case Offline = 'offline';
    case ManualTransfer = 'manual_transfer';
    case Gateway = 'gateway';
}
