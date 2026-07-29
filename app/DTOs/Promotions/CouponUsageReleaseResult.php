<?php

namespace App\DTOs\Promotions;

use App\Models\CouponUsageRelease;

final readonly class CouponUsageReleaseResult
{
    public const RELEASED = 'released';

    public const ALREADY_RELEASED = 'already_released';

    public const NOT_APPLICABLE = 'not_applicable';

    public function __construct(
        public string $outcome,
        public ?CouponUsageRelease $release = null,
    ) {}
}
