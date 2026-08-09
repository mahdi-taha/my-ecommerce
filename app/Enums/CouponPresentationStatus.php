<?php

namespace App\Enums;

enum CouponPresentationStatus: string
{
    case Active = 'active';
    case Scheduled = 'scheduled';
    case Expired = 'expired';
    case UsageExhausted = 'usage_exhausted';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Scheduled => 'Scheduled',
            self::Expired => 'Expired',
            self::UsageExhausted => 'Usage Exhausted',
            self::Inactive => 'Inactive',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'bg-success',
            self::Scheduled => 'bg-info',
            self::Expired => 'bg-secondary',
            self::UsageExhausted => 'bg-warning text-dark',
            self::Inactive => 'bg-danger',
        };
    }
}
