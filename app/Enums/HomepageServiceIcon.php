<?php

namespace App\Enums;

enum HomepageServiceIcon: string
{
    case Return = 'return';
    case Shipping = 'shipping';
    case Support = 'support';
    case Gift = 'gift';
    case Payment = 'payment';
    case Security = 'security';
    case Service = 'service';
    case Quality = 'quality';
    case Delivery = 'delivery';
    case Warranty = 'warranty';

    public function label(): string
    {
        return match ($this) {
            self::Return => 'Returns',
            self::Shipping => 'Shipping',
            self::Support => 'Support',
            self::Gift => 'Gift',
            self::Payment => 'Payment',
            self::Security => 'Security',
            self::Service => 'Customer Service',
            self::Quality => 'Quality',
            self::Delivery => 'Delivery',
            self::Warranty => 'Warranty',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Return => 'fas fa-sync-alt',
            self::Shipping => 'fas fa-shipping-fast',
            self::Support => 'fas fa-life-ring',
            self::Gift => 'fas fa-gift',
            self::Payment => 'fas fa-credit-card',
            self::Security => 'fas fa-lock',
            self::Service => 'fas fa-headset',
            self::Quality => 'fas fa-award',
            self::Delivery => 'fas fa-truck',
            self::Warranty => 'fas fa-shield-alt',
        };
    }
}
