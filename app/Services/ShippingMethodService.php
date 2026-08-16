<?php

namespace App\Services;

use App\Models\ShippingMethod;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            $shippingMethod = $this->lockedMethod($shippingMethod);

            if ($shippingMethod->is_active && ! (bool) ($data['is_active'] ?? true)) {
                $this->ensureAnotherActiveMethodExists($shippingMethod);
            }

            $shippingMethod->update($data);

            return $shippingMethod->fresh();
        });
    }

    public function updateStatus(ShippingMethod $shippingMethod, bool $isActive): ShippingMethod
    {
        return $this->update($shippingMethod, ['is_active' => $isActive]);
    }

    public function delete(ShippingMethod $shippingMethod): void
    {
        DB::transaction(function () use ($shippingMethod): void {
            $shippingMethod = $this->lockedMethod($shippingMethod);

            if ($shippingMethod->is_active) {
                $this->ensureAnotherActiveMethodExists($shippingMethod);
            }

            $shippingMethod->delete();
        });
    }

    private function lockedMethod(ShippingMethod $shippingMethod): ShippingMethod
    {
        $methods = ShippingMethod::query()
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $locked = $methods->firstWhere('id', $shippingMethod->getKey());

        if (! $locked) {
            throw (new ModelNotFoundException)->setModel(ShippingMethod::class, [$shippingMethod->getKey()]);
        }

        return $locked;
    }

    private function ensureAnotherActiveMethodExists(ShippingMethod $shippingMethod): void
    {
        if (ShippingMethod::query()
            ->where('is_active', true)
            ->whereKeyNot($shippingMethod->getKey())
            ->doesntExist()) {
            throw ValidationException::withMessages([
                'is_active' => 'At least one shipping method must remain active.',
            ]);
        }
    }
}
