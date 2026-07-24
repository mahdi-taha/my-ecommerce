<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductInventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class InventoryService
{
    public function deductOrderStock(
        Order $order,
        array $requirements,
        ?int $userId = null
    ): array {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Order stock deductions require an active database transaction.');
        }

        if (empty($requirements)) {
            throw ValidationException::withMessages([
                'requirements' => 'At least one inventory requirement is required.',
            ]);
        }

        $normalizedRequirements = [];

        foreach ($requirements as $productId => $requiredQuantity) {
            if (! is_int($productId) || $productId <= 0) {
                throw ValidationException::withMessages([
                    'requirements' => 'Every inventory requirement must use a valid positive product ID.',
                ]);
            }

            if (! is_numeric($requiredQuantity)) {
                throw ValidationException::withMessages([
                    "requirements.{$productId}" => 'The required quantity must be numeric.',
                ]);
            }

            $quantity = (float) $requiredQuantity;

            if (! is_finite($quantity) || $quantity <= 0) {
                throw ValidationException::withMessages([
                    "requirements.{$productId}" => 'The required quantity must be greater than zero.',
                ]);
            }

            $quantity = $this->normalizeDecimal($quantity);

            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    "requirements.{$productId}" => 'The required quantity must be at least 0.0001.',
                ]);
            }

            $normalizedRequirements[$productId] = $quantity;
        }

        ksort($normalizedRequirements, SORT_NUMERIC);

        $inventories = ProductInventory::query()
            ->with('product')
            ->whereIn('product_id', array_keys($normalizedRequirements))
            ->orderBy('product_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('product_id');

        $deductions = [];

        foreach ($normalizedRequirements as $productId => $requiredQuantity) {
            $inventory = $inventories->get($productId);

            if (! $inventory || ! $inventory->product) {
                throw ValidationException::withMessages([
                    "requirements.{$productId}" => 'The product does not have an inventory record.',
                ]);
            }

            $this->ensureInventoryEligible($inventory->product);

            $quantityBefore = (float) $inventory->quantity;
            $availableQuantity = $quantityBefore;

            if ($requiredQuantity > $availableQuantity) {
                throw ValidationException::withMessages([
                    "requirements.{$productId}" => "Insufficient stock. Required {$requiredQuantity}, available {$availableQuantity}.",
                ]);
            }

            $quantityAfter = $this->normalizeDecimal($quantityBefore - $requiredQuantity);
            $this->ensureSufficientStock($quantityAfter);
            $unitCost = $inventory->average_cost;

            $deductions[$productId] = [
                'inventory' => $inventory,
                'quantity' => $requiredQuantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'unit_cost' => $unitCost,
            ];
        }

        $productCosts = [];

        foreach ($deductions as $deduction) {
            $inventory = $deduction['inventory'];
            $movementQuantity = -$deduction['quantity'];

            $inventory->update(['quantity' => $deduction['quantity_after']]);

            $this->createMovement($inventory->product, [
                'type' => InventoryMovement::TYPE_SALE,
                'quantity' => $movementQuantity,
                'quantity_before' => $deduction['quantity_before'],
                'quantity_after' => $deduction['quantity_after'],
                'unit_cost' => $deduction['unit_cost'],
                'total_cost' => $movementQuantity * (float) $deduction['unit_cost'],
                'reference_type' => Order::class,
                'reference_id' => $order->getKey(),
                'notes' => null,
                'created_by' => $userId,
            ]);

            $productCosts[(int) $inventory->product_id] = $deduction['unit_cost'];
        }

        return $productCosts;
    }

    public function restoreOrderStock(
        Order $order,
        array $requirements,
        ?int $userId = null
    ): void {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Order stock restorations require an active database transaction.');
        }

        if (empty($requirements)) {
            throw ValidationException::withMessages([
                'requirements' => 'At least one inventory requirement is required.',
            ]);
        }

        $normalizedRequirements = [];

        foreach ($requirements as $productId => $requiredQuantity) {
            if (! is_int($productId) || $productId <= 0) {
                throw ValidationException::withMessages([
                    'requirements' => 'Every inventory requirement must use a valid positive product ID.',
                ]);
            }

            if (! is_numeric($requiredQuantity)) {
                throw ValidationException::withMessages([
                    "requirements.{$productId}" => 'The required quantity must be numeric.',
                ]);
            }

            $quantity = (float) $requiredQuantity;

            if (! is_finite($quantity) || $quantity <= 0) {
                throw ValidationException::withMessages([
                    "requirements.{$productId}" => 'The required quantity must be greater than zero.',
                ]);
            }

            $quantity = $this->normalizeDecimal($quantity);

            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    "requirements.{$productId}" => 'The required quantity must be at least 0.0001.',
                ]);
            }

            $normalizedRequirements[$productId] = $quantity;
        }

        ksort($normalizedRequirements, SORT_NUMERIC);

        $inventories = ProductInventory::query()
            ->with('product')
            ->whereIn('product_id', array_keys($normalizedRequirements))
            ->orderBy('product_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('product_id');

        $restorations = [];

        foreach ($normalizedRequirements as $productId => $requiredQuantity) {
            $inventory = $inventories->get($productId);

            if (! $inventory || ! $inventory->product) {
                throw ValidationException::withMessages([
                    "requirements.{$productId}" => 'The product does not have an inventory record.',
                ]);
            }

            $this->ensureInventoryEligible($inventory->product);

            $quantityBefore = (float) $inventory->quantity;
            $quantityAfter = $this->normalizeDecimal($quantityBefore + $requiredQuantity);
            $unitCost = $this->originalSaleUnitCost($order, $productId);

            if (! is_finite($quantityAfter)) {
                throw ValidationException::withMessages([
                    "requirements.{$productId}" => 'The restored inventory quantity is invalid.',
                ]);
            }

            $restorations[$productId] = [
                'inventory' => $inventory,
                'quantity' => $requiredQuantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'unit_cost' => $unitCost,
            ];
        }

        foreach ($restorations as $restoration) {
            $inventory = $restoration['inventory'];

            $inventory->update(['quantity' => $restoration['quantity_after']]);

            $this->createMovement($inventory->product, [
                'type' => InventoryMovement::TYPE_RETURN,
                'quantity' => $restoration['quantity'],
                'quantity_before' => $restoration['quantity_before'],
                'quantity_after' => $restoration['quantity_after'],
                'unit_cost' => $restoration['unit_cost'],
                'total_cost' => $restoration['quantity'] * (float) $restoration['unit_cost'],
                'reference_type' => Order::class,
                'reference_id' => $order->getKey(),
                'notes' => null,
                'created_by' => $userId,
            ]);
        }
    }

    public function setOpeningStock(Product $product, array $data, int $userId): ProductInventory
    {
        return DB::transaction(function () use ($product, $data, $userId) {
            $this->ensureInventoryEligible($product);
            $inventory = $this->getLockedInventory($product);

            if ($product->inventoryMovements()->exists()) {
                throw ValidationException::withMessages([
                    'product_id' => 'Opening stock can only be recorded before the first inventory movement.',
                ]);
            }

            $quantityBefore = (float) $inventory->quantity;
            $quantityAfter = $this->positiveDecimal(
                $data['quantity'] ?? null,
                'quantity',
                'Opening quantity'
            );
            $averageCost = $this->positiveDecimal(
                $data['unit_cost'] ?? null,
                'unit_cost',
                'Opening unit cost'
            );

            $this->createMovement($product, [
                'type' => InventoryMovement::TYPE_OPENING,
                'quantity' => $quantityAfter - $quantityBefore,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'unit_cost' => $averageCost,
                'total_cost' => $quantityAfter * $averageCost,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $inventory->update([
                'quantity' => $quantityAfter,
                'average_cost' => $averageCost,
            ]);

            return $inventory->refresh();
        });
    }

    public function receiveStock(Product $product, array $data, int $userId): ProductInventory
    {
        return DB::transaction(function () use ($product, $data, $userId) {
            $this->ensureInventoryEligible($product);
            $inventory = $this->getLockedInventory($product);
            $receivedQuantity = $this->positiveDecimal(
                $data['quantity'] ?? null,
                'quantity',
                'Received quantity'
            );
            $unitCost = $this->nonNegativeDecimal(
                $data['unit_cost'] ?? null,
                'unit_cost',
                'Unit cost'
            );
            $quantityBefore = (float) $inventory->quantity;
            $quantityAfter = $this->normalizeDecimal($quantityBefore + $receivedQuantity);
            $averageCost = $this->calculateWeightedAverageCost(
                $quantityBefore,
                (float) $inventory->average_cost,
                $receivedQuantity,
                $unitCost
            );

            $this->createMovement($product, [
                'type' => InventoryMovement::TYPE_RECEIPT,
                'quantity' => $receivedQuantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'unit_cost' => $unitCost,
                'total_cost' => $receivedQuantity * $unitCost,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $inventory->update([
                'quantity' => $quantityAfter,
                'average_cost' => $averageCost,
            ]);

            return $inventory->refresh();
        });
    }

    public function adjustStock(Product $product, array $data, int $userId): ProductInventory
    {
        return DB::transaction(function () use ($product, $data, $userId) {
            $this->ensureInventoryEligible($product);
            $inventory = $this->getLockedInventory($product);

            if (! isset($data['direction']) || ! in_array($data['direction'], ['increase', 'decrease'], true)) {
                throw ValidationException::withMessages([
                    'direction' => 'The inventory adjustment direction must be increase or decrease.',
                ]);
            }

            if (! isset($data['notes']) || ! is_string($data['notes']) || trim($data['notes']) === '') {
                throw ValidationException::withMessages([
                    'notes' => 'Notes are required for inventory adjustments.',
                ]);
            }

            $quantityBefore = (float) $inventory->quantity;
            $requestedQuantity = $this->positiveDecimal(
                $data['quantity'] ?? null,
                'quantity',
                'Adjustment quantity'
            );
            $movementQuantity = $data['direction'] === 'decrease'
                ? -$requestedQuantity
                : $requestedQuantity;
            $quantityAfter = $this->normalizeDecimal($quantityBefore + $movementQuantity);

            $this->ensureSufficientStock($quantityAfter);
            $this->createMovement($product, [
                'type' => InventoryMovement::TYPE_ADJUSTMENT,
                'quantity' => $movementQuantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'unit_cost' => $inventory->average_cost,
                'total_cost' => $movementQuantity * (float) $inventory->average_cost,
                'notes' => $data['notes'],
                'created_by' => $userId,
            ]);

            $inventory->update(['quantity' => $quantityAfter]);

            return $inventory->refresh();
        });
    }

    public function recordStockCount(Product $product, array $data, int $userId): ProductInventory
    {
        return DB::transaction(function () use ($product, $data, $userId) {
            $this->ensureInventoryEligible($product);
            $inventory = $this->getLockedInventory($product);

            if (! isset($data['notes']) || ! is_string($data['notes']) || trim($data['notes']) === '') {
                throw ValidationException::withMessages([
                    'notes' => 'Notes are required for stock counts.',
                ]);
            }

            $quantityBefore = (float) $inventory->quantity;
            $quantityAfter = $this->nonNegativeDecimal(
                $data['counted_quantity'] ?? null,
                'counted_quantity',
                'Counted quantity'
            );
            $movementQuantity = $this->normalizeDecimal($quantityAfter - $quantityBefore);

            $this->ensureSufficientStock($quantityAfter);
            $this->createMovement($product, [
                'type' => InventoryMovement::TYPE_STOCK_COUNT,
                'quantity' => $movementQuantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'unit_cost' => $inventory->average_cost,
                'total_cost' => $movementQuantity * (float) $inventory->average_cost,
                'notes' => $data['notes'],
                'created_by' => $userId,
            ]);

            $inventory->update(['quantity' => $quantityAfter]);

            return $inventory->refresh();
        });
    }

    public function updateLowStockAlert(Product $product, mixed $threshold): ProductInventory
    {
        return DB::transaction(function () use ($product, $threshold) {
            $this->ensureInventoryEligible($product);
            $inventory = $this->getLockedInventory($product);
            $inventory->update([
                'low_stock_alert' => $threshold === null || $threshold === ''
                    ? null
                    : $this->nonNegativeDecimal(
                        $threshold,
                        'low_stock_alert',
                        'Low-stock threshold'
                    ),
            ]);

            return $inventory->refresh();
        });
    }

    private function ensureInventoryEligible(Product $product): void
    {
        if ($product->type !== 'simple') {
            throw ValidationException::withMessages([
                'product_id' => 'Inventory operations are allowed only for standalone simple products and variants.',
            ]);
        }
    }

    private function getLockedInventory(Product $product): ProductInventory
    {
        $inventory = ProductInventory::query()
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->first();

        if (! $inventory) {
            $timestamp = now();

            DB::table('product_inventories')->insertOrIgnore([
                'product_id' => $product->id,
                'quantity' => 0,
                'average_cost' => 0,
                'low_stock_alert' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $inventory = ProductInventory::query()
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        return $inventory;
    }

    private function createMovement(Product $product, array $data): InventoryMovement
    {
        return $product->inventoryMovements()->create($data);
    }

    private function originalSaleUnitCost(Order $order, int $productId): string
    {
        $saleMovements = InventoryMovement::query()
            ->where('product_id', $productId)
            ->where('type', InventoryMovement::TYPE_SALE)
            ->where('reference_type', Order::class)
            ->where('reference_id', $order->getKey())
            ->get(['id', 'unit_cost']);

        if ($saleMovements->count() !== 1 || $saleMovements->first()->unit_cost === null) {
            throw ValidationException::withMessages([
                "requirements.{$productId}" => 'The original Sale movement cost could not be determined.',
            ]);
        }

        return $saleMovements->first()->unit_cost;
    }

    private function calculateWeightedAverageCost(
        float $currentQuantity,
        float $currentAverageCost,
        float $receivedQuantity,
        float $receivedUnitCost
    ): float {
        $newQuantity = $currentQuantity + $receivedQuantity;

        if ($newQuantity <= 0) {
            return $this->normalizeDecimal($receivedUnitCost);
        }

        return $this->normalizeDecimal(
            (($currentQuantity * $currentAverageCost) + ($receivedQuantity * $receivedUnitCost))
            / $newQuantity
        );
    }

    private function ensureSufficientStock(float $newQuantity): void
    {
        if ($newQuantity < 0) {
            throw ValidationException::withMessages([
                'quantity' => 'This operation would make inventory quantity negative.',
            ]);
        }
    }

    private function normalizeDecimal(mixed $value): float
    {
        return round((float) $value, 4);
    }

    private function positiveDecimal(mixed $value, string $field, string $label): float
    {
        $decimal = $this->validatedDecimal($value, $field, $label);

        if ($decimal <= 0) {
            throw ValidationException::withMessages([
                $field => "{$label} must be greater than zero.",
            ]);
        }

        return $decimal;
    }

    private function nonNegativeDecimal(mixed $value, string $field, string $label): float
    {
        $decimal = $this->validatedDecimal($value, $field, $label);

        if ($decimal < 0) {
            throw ValidationException::withMessages([
                $field => "{$label} must be zero or greater.",
            ]);
        }

        return $decimal;
    }

    private function validatedDecimal(mixed $value, string $field, string $label): float
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                $field => "{$label} must be numeric.",
            ]);
        }

        $decimal = (float) $value;

        if (! is_finite($decimal)) {
            throw ValidationException::withMessages([
                $field => "{$label} is invalid.",
            ]);
        }

        return $this->normalizeDecimal($decimal);
    }
}
