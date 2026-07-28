<?php

namespace App\Enums;

enum ShippingMethodType: string
{
    case Delivery = 'delivery';
    case Pickup = 'pickup';
}
