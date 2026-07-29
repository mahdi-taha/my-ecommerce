<?php

namespace App\Services;

use App\Enums\CouponType;
use App\Models\Coupon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public function create(array $data): Coupon
    {
        return DB::transaction(fn () => Coupon::create($this->writableData($data)));
    }

    public function update(Coupon $coupon, array $data): Coupon
    {
        return DB::transaction(function () use ($coupon, $data): Coupon {
            $coupon = Coupon::query()->whereKey($coupon->getKey())->lockForUpdate()->firstOrFail();
            $writable = $this->writableData($data);

            if ($coupon->usages()->exists() && $writable['code'] !== $coupon->code) {
                throw ValidationException::withMessages([
                    'code' => 'The Coupon code cannot be changed after its first usage.',
                ]);
            }

            $coupon->update($writable);

            return $coupon->fresh();
        });
    }

    public function deactivate(Coupon $coupon): Coupon
    {
        return DB::transaction(function () use ($coupon): Coupon {
            $coupon = Coupon::query()->whereKey($coupon->getKey())->lockForUpdate()->firstOrFail();
            $coupon->update(['is_active' => false]);

            return $coupon->fresh();
        });
    }

    public function deleteUnused(Coupon $coupon): void
    {
        DB::transaction(function () use ($coupon): void {
            $coupon = Coupon::query()->whereKey($coupon->getKey())->lockForUpdate()->firstOrFail();

            if ($coupon->usages()->exists()) {
                throw ValidationException::withMessages([
                    'coupon' => 'Coupons with usage history cannot be deleted. Deactivate the Coupon instead.',
                ]);
            }

            $coupon->delete();
        });
    }

    public function normalizeCode(mixed $code): string
    {
        return mb_strtoupper(trim((string) $code), 'UTF-8');
    }

    private function writableData(array $data): array
    {
        $code = $this->normalizeCode($data['code'] ?? '');
        $name = trim((string) ($data['name'] ?? ''));
        $type = $data['type'] instanceof CouponType
            ? $data['type']
            : CouponType::tryFrom((string) ($data['type'] ?? ''));
        $value = (float) ($data['value'] ?? 0);

        if ($code === '' || preg_match('/^[A-Z0-9_-]+$/', $code) !== 1) {
            throw ValidationException::withMessages([
                'code' => 'The Coupon code must contain only ASCII letters, numbers, dashes, and underscores.',
            ]);
        }

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'The Coupon name is required.',
            ]);
        }

        if (! $type
            || $value <= 0
            || ($type === CouponType::Percentage && $value > 100)) {
            throw ValidationException::withMessages([
                'value' => 'The Coupon value is invalid for the selected type.',
            ]);
        }

        foreach (['usage_limit', 'per_customer_usage_limit'] as $field) {
            if (isset($data[$field]) && (int) $data[$field] <= 0) {
                throw ValidationException::withMessages([
                    $field => 'Usage limits must be positive integers when provided.',
                ]);
            }
        }

        if (isset($data['minimum_subtotal']) && (float) $data['minimum_subtotal'] < 0) {
            throw ValidationException::withMessages([
                'minimum_subtotal' => 'The minimum subtotal cannot be negative.',
            ]);
        }

        $startsAt = $this->utcTimestamp($data['starts_at'] ?? null);
        $endsAt = $this->utcTimestamp($data['ends_at'] ?? null);

        if ($startsAt && $endsAt && $endsAt->lte($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'The end date must be later than the start date.',
            ]);
        }

        return [
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'value' => number_format($value, 4, '.', ''),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'minimum_subtotal' => isset($data['minimum_subtotal'])
                ? number_format((float) $data['minimum_subtotal'], 4, '.', '')
                : null,
            'usage_limit' => isset($data['usage_limit']) ? (int) $data['usage_limit'] : null,
            'per_customer_usage_limit' => isset($data['per_customer_usage_limit'])
                ? (int) $data['per_customer_usage_limit']
                : null,
            'is_first_order_only' => (bool) ($data['is_first_order_only'] ?? false),
        ];
    }

    private function utcTimestamp(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse((string) $value, $this->storeTimezone())->utc();
    }

    private function storeTimezone(): string
    {
        return (string) setting('localization.timezone', config('app.timezone'));
    }
}
