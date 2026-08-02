<?php

namespace App\Services;

use App\DTOs\Checkout\CheckoutSummary;
use App\DTOs\Checkout\CheckoutValidationError;
use App\DTOs\Checkout\CheckoutValidationResult;
use App\Enums\AccountType;
use App\Enums\CartItemType;
use App\Enums\NotificationEventCode;
use App\Enums\PaymentMethodType;
use App\Events\CommerceEventOccurred;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminOrderCreationService
{
    private const SUPPORTED_PAYMENT_METHODS = [
        'cash_on_delivery',
        'manual_wallet_transfer',
        'manual_bank_transfer',
    ];

    public function __construct(
        private CheckoutCartValidator $validator,
        private CheckoutService $checkoutService,
        private OrderSnapshotFactory $snapshotFactory,
        private OrderService $orderService,
    ) {}

    public function summarize(array $data): CheckoutSummary
    {
        $customer = $this->eligibleCustomer((int) $data['customer_id']);
        $this->resolveAddress($data, $customer);

        return $this->withOrderLocale(
            fn (): CheckoutSummary => $this->checkoutService->summarizeLocked(
                $this->validation($data, false),
                null
            )
        );
    }

    public function create(array $data, string $creationKey): Order
    {
        if ($existing = Order::query()->where('admin_creation_key', $creationKey)->first()) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($data, $creationKey): Order {
                if ($existing = Order::query()->where('admin_creation_key', $creationKey)->first()) {
                    return $existing;
                }

                $timestamp = now();
                $customer = $this->eligibleCustomer((int) $data['customer_id'], true);
                $address = $this->resolveAddress($data, $customer, true);
                $locale = $this->orderLocale();
                $validation = $this->withLocale(
                    $locale,
                    fn (): CheckoutValidationResult => $this->validation($data, true)
                );

                if (! $validation->isValid()) {
                    throw ValidationException::withMessages($this->validationMessages($validation));
                }

                $summary = $this->withLocale(
                    $locale,
                    fn (): CheckoutSummary => $this->checkoutService->summarizeLocked($validation, null)
                );

                if (! $summary->isValid()) {
                    throw ValidationException::withMessages(
                        collect($summary->errors)->mapWithKeys(
                            fn (array $error): array => [
                                $error['field'] ?? 'items' => [$error['message']],
                            ]
                        )->all()
                    );
                }

                $orderData = $this->snapshotFactory->make(
                    customerSnapshot: [
                        'user_id' => $customer->getKey(),
                        'email' => $customer->email,
                        'first_name' => $customer->first_name,
                        'last_name' => $customer->last_name,
                        'phone' => $customer->phone,
                    ],
                    validatedItems: $validation->items,
                    summary: $summary,
                    shippingMethod: $validation->shippingMethod,
                    resolvedAddress: $address,
                    paymentMethodCode: $validation->paymentMethod->code,
                    locale: $locale,
                    timestamp: $timestamp,
                );
                $orderData['admin_creation_key'] = $creationKey;
                $order = $this->orderService->createWithinTransaction($orderData);

                CommerceEventOccurred::dispatch(
                    NotificationEventCode::OrderPlaced,
                    'order',
                    (int) $order->getKey()
                );

                return $order;
            });
        } catch (QueryException $exception) {
            if (! $this->isCreationKeyViolation($exception)) {
                throw $exception;
            }

            return Order::query()->where('admin_creation_key', $creationKey)->firstOrFail();
        }
    }

    private function validation(array $data, bool $lock): CheckoutValidationResult
    {
        $submittedItems = collect($data['items'])->values();
        $submittedProductIds = $submittedItems->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $references = Product::query()
            ->whereIn('id', $submittedProductIds)
            ->get(['id', 'configurable_id']);
        $productIds = $submittedProductIds
            ->merge($references->pluck('configurable_id')->filter())
            ->merge($submittedItems->pluck('parent_product_id')->filter())
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values();
        $productsQuery = Product::query()->whereIn('id', $productIds)->orderBy('id');
        $inventoriesQuery = ProductInventory::query()
            ->whereIn('product_id', $submittedProductIds)
            ->orderBy('product_id');
        $shippingQuery = ShippingMethod::query()
            ->where('code', $data['shipping_method'])
            ->where('is_active', true);
        $paymentQuery = PaymentMethod::query()
            ->where('code', $data['payment_method'])
            ->whereIn('code', self::SUPPORTED_PAYMENT_METHODS)
            ->whereIn('type', [
                PaymentMethodType::Offline->value,
                PaymentMethodType::ManualTransfer->value,
            ])
            ->where('is_active', true);

        if ($lock) {
            $productsQuery->lockForUpdate();
            $inventoriesQuery->lockForUpdate();
            $shippingQuery->lockForUpdate();
            $paymentQuery->lockForUpdate();
        }

        $products = $productsQuery->get();
        $inventories = $inventoriesQuery->get();
        $items = $submittedItems->map(function (array $submitted, int $index): CartItem {
            $item = new CartItem([
                'product_id' => (int) $submitted['product_id'],
                'product_type' => $submitted['product_type'],
                'configuration_hash' => hash('sha256', json_encode($submitted, JSON_THROW_ON_ERROR)),
                'quantity' => $submitted['quantity'],
            ]);
            $item->setAttribute('id', $index + 1);

            return $item;
        });
        $validation = $this->validator->validateLockedItems(
            $items,
            $products,
            $inventories,
            $shippingQuery->first(),
            $paymentQuery->first()
        );
        $configurationErrors = $this->configurationErrors($submittedItems, $products);

        return $configurationErrors === []
            ? $validation
            : new CheckoutValidationResult(
                $validation->items,
                $validation->shippingMethod,
                $validation->paymentMethod,
                [...$validation->errors, ...$configurationErrors]
            );
    }

    private function configurationErrors($submittedItems, $products): array
    {
        $byId = $products->keyBy(fn (Product $product): int => (int) $product->getKey());
        $errors = [];

        foreach ($submittedItems as $index => $submitted) {
            $product = $byId->get((int) $submitted['product_id']);
            $type = CartItemType::tryFrom((string) $submitted['product_type']);
            $options = collect($submitted['options'] ?? [])
                ->mapWithKeys(fn ($id, $attributeId): array => [(int) $attributeId => (int) $id])
                ->sortKeys()
                ->all();

            if ($type === CartItemType::Simple) {
                if (($submitted['parent_product_id'] ?? null) !== null || $options !== []) {
                    $errors[] = $this->configurationError($index, $product?->getKey());
                }

                continue;
            }

            $actual = $product?->attributeValues
                ?->whereNotNull('attribute_option_id')
                ->pluck('attribute_option_id', 'attribute_id')
                ->map(fn ($id): int => (int) $id)
                ->sortKeys()
                ->all() ?? [];

            if (! $product
                || (int) $product->configurable_id !== (int) ($submitted['parent_product_id'] ?? 0)
                || $options === []
                || $options !== $actual) {
                $errors[] = $this->configurationError($index, $product?->getKey());
            }
        }

        return $errors;
    }

    private function configurationError(int $index, ?int $productId): CheckoutValidationError
    {
        return new CheckoutValidationError(
            'invalid_configuration',
            "items.{$index}.options",
            'The selected Configurable Product options are invalid.',
            $index + 1,
            $productId,
        );
    }

    private function eligibleCustomer(int $customerId, bool $lock = false): User
    {
        $query = User::query()
            ->whereKey($customerId)
            ->where('account_type', AccountType::Customer->value)
            ->where('is_active', true);

        if ($lock) {
            $query->lockForUpdate();
        }

        $customer = $query->first();

        if (! $customer) {
            throw ValidationException::withMessages(['customer_id' => 'The selected Customer is unavailable.']);
        }

        return $customer;
    }

    private function resolveAddress(array $data, User $customer, bool $lock = false): array
    {
        if ($data['address_source'] === 'saved') {
            $query = $customer->customerAddresses()->whereKey($data['saved_address_id']);

            if ($lock) {
                $query->lockForUpdate();
            }

            $address = $query->first();

            if (! $address) {
                throw ValidationException::withMessages([
                    'saved_address_id' => 'The selected Customer address is unavailable.',
                ]);
            }

            return [
                'first_name' => $address->first_name,
                'last_name' => $address->last_name,
                'company' => $address->company,
                'email' => $customer->email,
                'phone' => $address->phone,
                'address_line_1' => $address->address_line_1,
                'address_line_2' => $address->address_line_2,
                'city' => $address->city,
                'state' => $address->state,
                'postal_code' => $address->postal_code,
                'country_code' => $address->country_code,
            ];
        }

        $address = collect($data['manual_address'])->only([
            'first_name',
            'last_name',
            'company',
            'email',
            'phone',
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'postal_code',
            'country_code',
        ])->all();
        $address['email'] = $address['email'] ?? $customer->email;
        $address['country_code'] = strtoupper((string) $address['country_code']);

        return $address;
    }

    private function validationMessages(CheckoutValidationResult $validation): array
    {
        return collect($validation->errors)
            ->groupBy(fn (CheckoutValidationError $error): string => $error->field)
            ->map(fn ($errors): array => $errors->pluck('message')->all())
            ->all();
    }

    private function withOrderLocale(callable $callback): mixed
    {
        return $this->withLocale($this->orderLocale(), $callback);
    }

    private function withLocale(string $locale, callable $callback): mixed
    {
        $previous = App::getLocale();
        App::setLocale($locale);

        try {
            return $callback();
        } finally {
            App::setLocale($previous);
        }
    }

    private function orderLocale(): string
    {
        $locale = (string) setting('localization.default_locale', config('app.locale'));

        return in_array($locale, ['en', 'ar'], true) ? $locale : 'en';
    }

    private function isCreationKeyViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            && str_contains($exception->getMessage(), 'admin_creation_key');
    }
}
