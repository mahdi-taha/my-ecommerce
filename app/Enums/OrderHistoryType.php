<?php

namespace App\Enums;

enum OrderHistoryType: string
{
    case Order = 'order';
    case Payment = 'payment';
    case Fulfillment = 'fulfillment';
}
