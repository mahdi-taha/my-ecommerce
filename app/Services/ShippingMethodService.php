<?php

namespace App\Services;

use App\Models\ShippingMethod;
use Illuminate\Support\Facades\DB;

class ShippingMethodService
{
    public function create(array $data): ShippingMethod
    {
        return DB::transaction(fn () => ShippingMethod::create($data));
    }

    public function update(ShippingMethod $shippingMethod, array $data): ShippingMethod
    {
        unset($data['code']);

        return DB::transaction(function () use ($shippingMethod, $data): ShippingMethod {
            $shippingMethod = ShippingMethod::query()
                ->whereKey($shippingMethod->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $shippingMethod->update($data);

            return $shippingMethod->fresh();
        });
    }

    public function updateStatus(ShippingMethod $shippingMethod, bool $isActive): ShippingMethod
    {
        return $this->update($shippingMethod, ['is_active' => $isActive]);
    }
}
