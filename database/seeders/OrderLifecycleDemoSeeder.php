<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderService;
use App\Services\OrderStatusService;
use App\Services\PaymentStatusService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class OrderLifecycleDemoSeeder extends Seeder
{
    public function run(
        OrderService $orderService,
        OrderStatusService $orderStatusService,
        PaymentStatusService $paymentStatusService
    ): void {
        if (app()->environment('production')) {
            throw new RuntimeException('OrderLifecycleDemoSeeder cannot run in production.');
        }

        $admin = User::query()
            ->where('email', 'test@example.com')
            ->first();

        if (! $admin) {
            throw new RuntimeException(
                'Order lifecycle demo seeding requires the seeded admin user test@example.com.'
            );
        }

        if (! $this->eligibleProducts()->exists()) {
            throw new RuntimeException(
                'Order lifecycle demo seeding requires at least one active standalone simple product or configurable variant with at least 1 available inventory. Run the catalog and inventory seeders first.'
            );
        }

        $currencyCode = Setting::query()
            ->where('group', 'currency')
            ->where('key', 'default_currency')
            ->value('value') ?? 'USD';

        $scenarios = [
            [
                'key' => 'pending-cod',
                'email' => 'orders-demo-pending-cod@example.test',
                'name' => 'Nadine Haddad',
                'payment_method' => 'cash_on_delivery',
                'description' => 'Pending — Cash on Delivery',
            ],
            [
                'key' => 'pending-online-retry',
                'email' => 'orders-demo-pending-online@example.test',
                'name' => 'Karim Saad',
                'payment_method' => 'manual_wallet_transfer',
                'description' => 'Pending — Manual Wallet Transfer after one failed attempt and retry',
            ],
            [
                'key' => 'pending-online-failed',
                'email' => 'orders-demo-failed-online@example.test',
                'name' => 'Maya Nassar',
                'payment_method' => 'manual_wallet_transfer',
                'description' => 'Pending Order — Manual Wallet Transfer payment failed',
            ],
            [
                'key' => 'processing-cod',
                'email' => 'orders-demo-processing-cod@example.test',
                'name' => 'Rami Khoury',
                'payment_method' => 'cash_on_delivery',
                'description' => 'Processing — Cash on Delivery',
            ],
            [
                'key' => 'processing-online-paid',
                'email' => 'orders-demo-processing-paid@example.test',
                'name' => 'Lina Daher',
                'payment_method' => 'manual_wallet_transfer',
                'description' => 'Processing — Manual Wallet Transfer paid before processing',
            ],
            [
                'key' => 'out-for-delivery-paid',
                'email' => 'orders-demo-dispatched-paid@example.test',
                'name' => 'Tarek Mansour',
                'payment_method' => 'manual_wallet_transfer',
                'description' => 'Out for Delivery — Paid',
            ],
            [
                'key' => 'out-for-delivery-cod',
                'email' => 'orders-demo-dispatched-cod@example.test',
                'name' => 'Sara Younes',
                'payment_method' => 'cash_on_delivery',
                'description' => 'Out for Delivery — Unpaid Cash on Delivery',
            ],
            [
                'key' => 'completed-after-retries',
                'email' => 'orders-demo-completed@example.test',
                'name' => 'Omar Salem',
                'payment_method' => 'manual_wallet_transfer',
                'description' => 'Completed — Paid and Fulfilled after two payment retries',
            ],
            [
                'key' => 'cancelled-pending',
                'email' => 'orders-demo-cancelled-pending@example.test',
                'name' => 'Dalia Farah',
                'payment_method' => 'cash_on_delivery',
                'description' => 'Cancelled before Processing',
            ],
            [
                'key' => 'cancelled-processing',
                'email' => 'orders-demo-cancelled-processing@example.test',
                'name' => 'Walid Harb',
                'payment_method' => 'cash_on_delivery',
                'description' => 'Cancelled after Processing with inventory restoration',
            ],
            [
                'key' => 'delivery-failed',
                'email' => 'orders-demo-delivery-failed@example.test',
                'name' => 'Reem Karam',
                'payment_method' => 'cash_on_delivery',
                'description' => 'Delivery Failed with inventory restoration',
            ],
        ];

        Auth::setUser($admin);
        $timelineStart = now()->subDays(30)->startOfDay()->addHours(10);

        try {
            foreach ($scenarios as $index => $scenario) {
                $existingOrder = Order::query()
                    ->where('customer_email', $scenario['email'])
                    ->first();

                if ($existingOrder) {
                    $this->reportOrder($existingOrder, $scenario['description'], 'existing');

                    continue;
                }

                $product = $this->productForScenario($index);
                $placedAt = $timelineStart->copy()->addDays($index * 2);

                Carbon::setTestNow($placedAt);

                $order = $this->createOrder(
                    $orderService,
                    $product,
                    $currencyCode,
                    $scenario
                );

                $this->applyLifecycle(
                    $order,
                    $scenario['key'],
                    $placedAt,
                    $orderStatusService,
                    $paymentStatusService
                );

                $this->reportOrder(
                    $order->fresh(),
                    $scenario['description'],
                    'created'
                );
            }
        } finally {
            Carbon::setTestNow();
            Auth::forgetUser();
        }
    }

    private function eligibleProducts()
    {
        return Product::query()
            ->where('type', 'simple')
            ->where('status', true)
            ->whereHas('inventory', function ($query) {
                $query->where('quantity', '>=', 1);
            });
    }

    private function productForScenario(int $index): Product
    {
        $products = $this->eligibleProducts()
            ->with([
                'translations',
                'images',
                'inventory',
                'configurable.translations',
                'configurable.images',
                'attributeValues.attribute.translations',
                'attributeValues.option.translations',
            ])
            ->orderByRaw('configurable_id IS NULL DESC')
            ->orderBy('id')
            ->get();

        if ($products->isEmpty()) {
            throw new RuntimeException(
                'Insufficient available inventory to seed every Order lifecycle scenario. Ensure active simple products or configurable variants have enough stock, then rerun the seeder.'
            );
        }

        return $products[$index % $products->count()];
    }

    private function createOrder(
        OrderService $orderService,
        Product $product,
        string $currencyCode,
        array $scenario
    ): Order {
        $item = $this->itemSnapshot($product);
        [$firstName, $lastName] = explode(' ', $scenario['name'], 2);
        $shippingTotal = 3.5;
        $grandTotal = round($item['row_total'] + $shippingTotal, 4);

        return $orderService->create([
            'user_id' => null,
            'customer_email' => $scenario['email'],
            'customer_first_name' => $firstName,
            'customer_last_name' => $lastName,
            'customer_phone' => '+961 1 000 000',
            'locale' => 'en',
            'currency_code' => $currencyCode,
            'payment_method' => $scenario['payment_method'],
            'subtotal' => $item['row_subtotal'],
            'discount_total' => 0,
            'shipping_total' => $shippingTotal,
            'tax_total' => 0,
            'grand_total' => $grandTotal,
            'customer_notes' => null,
            'admin_notes' => 'Demo lifecycle scenario: '.$scenario['description'].'.',
            'placed_at' => now()->toDateTimeString(),
            'items' => [$item],
            'billing_address' => $this->address($firstName, $lastName, $scenario['email']),
            'shipping_address' => $this->address($firstName, $lastName, $scenario['email']),
            'payment' => [
                'method' => $scenario['payment_method'],
                'amount' => $grandTotal,
                'transaction_reference' => null,
                'failure_message' => null,
            ],
        ]);
    }

    private function applyLifecycle(
        Order $order,
        string $scenario,
        Carbon $placedAt,
        OrderStatusService $orderStatusService,
        PaymentStatusService $paymentStatusService
    ): void {
        $currentTime = $placedAt->copy();

        $advanceTime = function () use (&$currentTime): void {
            $currentTime->addHours(3);
            Carbon::setTestNow($currentTime);
        };

        $run = function (callable $action) use ($advanceTime): Order {
            $advanceTime();

            return $action();
        };

        match ($scenario) {
            'pending-cod' => null,
            'pending-online-retry' => $this->pendingAfterRetry($order, $paymentStatusService, $run),
            'pending-online-failed' => $run(fn () => $paymentStatusService->markFailed($order)),
            'processing-cod' => $run(fn () => $orderStatusService->process($order)),
            'processing-online-paid' => $this->paidAndProcessed($order, $orderStatusService, $paymentStatusService, $run),
            'out-for-delivery-paid' => $this->paidAndDispatched($order, $orderStatusService, $paymentStatusService, $run),
            'out-for-delivery-cod' => $this->codDispatched($order, $orderStatusService, $run),
            'completed-after-retries' => $this->completedAfterRetries($order, $orderStatusService, $paymentStatusService, $run),
            'cancelled-pending' => $run(fn () => $orderStatusService->cancel($order)),
            'cancelled-processing' => $this->cancelAfterProcessing($order, $orderStatusService, $run),
            'delivery-failed' => $this->deliveryFailed($order, $orderStatusService, $run),
            default => throw new RuntimeException("Unknown Order lifecycle demo scenario: {$scenario}."),
        };
    }

    private function pendingAfterRetry(Order $order, PaymentStatusService $service, callable $run): void
    {
        $order = $run(fn () => $service->markFailed($order));
        $run(fn () => $service->retry($order));
    }

    private function paidAndProcessed(
        Order $order,
        OrderStatusService $orderStatusService,
        PaymentStatusService $paymentStatusService,
        callable $run
    ): void {
        $order = $run(fn () => $paymentStatusService->markPaid($order));
        $run(fn () => $orderStatusService->process($order));
    }

    private function paidAndDispatched(
        Order $order,
        OrderStatusService $orderStatusService,
        PaymentStatusService $paymentStatusService,
        callable $run
    ): void {
        $order = $run(fn () => $paymentStatusService->markPaid($order));
        $order = $run(fn () => $orderStatusService->process($order));
        $run(fn () => $orderStatusService->markOutForDelivery($order));
    }

    private function codDispatched(Order $order, OrderStatusService $service, callable $run): void
    {
        $order = $run(fn () => $service->process($order));
        $run(fn () => $service->markOutForDelivery($order));
    }

    private function completedAfterRetries(
        Order $order,
        OrderStatusService $orderStatusService,
        PaymentStatusService $paymentStatusService,
        callable $run
    ): void {
        $order = $run(fn () => $paymentStatusService->markFailed($order));
        $order = $run(fn () => $paymentStatusService->retry($order));
        $order = $run(fn () => $paymentStatusService->markFailed($order));
        $order = $run(fn () => $paymentStatusService->retry($order));
        $order = $run(fn () => $paymentStatusService->markPaid($order));
        $order = $run(fn () => $orderStatusService->process($order));
        $order = $run(fn () => $orderStatusService->markOutForDelivery($order));
        $run(fn () => $orderStatusService->fulfill($order));
    }

    private function cancelAfterProcessing(Order $order, OrderStatusService $service, callable $run): void
    {
        $order = $run(fn () => $service->process($order));
        $run(fn () => $service->cancel($order));
    }

    private function deliveryFailed(Order $order, OrderStatusService $service, callable $run): void
    {
        $order = $run(fn () => $service->process($order));
        $order = $run(fn () => $service->markOutForDelivery($order));
        $run(fn () => $service->markDeliveryFailed($order));
    }

    private function itemSnapshot(Product $product): array
    {
        $optionParts = $product->attributeValues
            ->sortBy('attribute_id')
            ->map(function ($value): array {
                $attributeName = $value->attribute?->translations
                    ->firstWhere('locale', 'en')?->admin_name
                    ?? $value->attribute?->code
                    ?? 'Attribute';
                $optionLabel = $value->option?->translations
                    ->firstWhere('locale', 'en')?->label
                    ?? 'Option #'.$value->attribute_option_id;

                return [
                    'attribute_id' => $value->attribute_id,
                    'attribute_option_id' => $value->attribute_option_id,
                    'attribute' => $attributeName,
                    'option' => $optionLabel,
                ];
            })
            ->values();

        $optionSummary = $optionParts
            ->map(fn (array $part) => $part['attribute'].': '.$part['option'])
            ->implode(' / ');
        $translatedProduct = $product->configurable ?? $product;
        $name = $translatedProduct->translations
            ->firstWhere('locale', 'en')?->name
            ?? $translatedProduct->translations->first()?->name
            ?? $product->sku;

        if ($product->configurable_id !== null && $optionSummary !== '') {
            $name .= ' - '.$optionSummary;
        }

        $image = $product->images->firstWhere('is_base', true)
            ?? $product->images->first()
            ?? $product->configurable?->images->firstWhere('is_base', true)
            ?? $product->configurable?->images->first();
        $unitPrice = (float) $product->price;

        return [
            'product_id' => $product->id,
            'product_type' => $product->configurable_id === null ? 'simple' : 'variant',
            'sku' => $product->sku,
            'product_number' => $product->product_number,
            'name' => $name,
            'option_summary' => $optionSummary !== '' ? $optionSummary : null,
            'image_path' => $image?->path,
            'configuration' => $optionParts->isNotEmpty() ? $optionParts->all() : null,
            'quantity' => 1,
            'original_unit_price' => $unitPrice,
            'unit_price' => $unitPrice,
            'tax_amount' => 0,
            'row_subtotal' => $unitPrice,
            'row_total' => $unitPrice,
            'unit_cost' => null,
            'is_inventory_item' => true,
        ];
    }

    private function address(string $firstName, string $lastName, string $email): array
    {
        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company' => null,
            'email' => $email,
            'phone' => '+961 1 000 000',
            'address_line_1' => 'Demo Street 1',
            'address_line_2' => null,
            'city' => 'Beirut',
            'state' => null,
            'postal_code' => null,
            'country_code' => 'LB',
        ];
    }

    private function reportOrder(Order $order, string $scenario, string $state): void
    {
        $productSummary = $order->items()
            ->get(['sku', 'name', 'product_type'])
            ->map(fn ($item) => $item->sku.' ('.$item->name.', '.$item->product_type.')')
            ->implode(', ');

        $this->command?->info(
            "[{$state}] {$order->order_number} | {$order->customer_email} | {$scenario} | {$productSummary}"
        );
    }
}
