<?php

namespace App\Enums;

enum FulfillmentStatus: string
{
    case Unfulfilled = 'unfulfilled';
    case OutForDelivery = 'out_for_delivery';
    case Fulfilled = 'fulfilled';
    case DeliveryFailed = 'delivery_failed';
}
