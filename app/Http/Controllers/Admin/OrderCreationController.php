<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethodType;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminOrderRequest;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\AdminOrderCreationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderCreationController extends Controller
{
    private const TOKEN_HASH_SESSION_KEY = 'admin_order_creation.token_hash';

    private const COMPLETED_SESSION_KEY = 'admin_order_creation.completed';

    private const SUPPORTED_PAYMENT_METHODS = [
        'cash_on_delivery',
        'manual_wallet_transfer',
        'manual_bank_transfer',
    ];

    public function __construct(private AdminOrderCreationService $orders) {}

    public function create(Request $request): View
    {
        $oldToken = old('admin_creation_token');
        $expectedHash = (string) $request->session()->get(self::TOKEN_HASH_SESSION_KEY, '');
        $reuseOldToken = is_string($oldToken)
            && strlen($oldToken) === 64
            && $expectedHash !== ''
            && hash_equals($expectedHash, hash('sha256', $oldToken));
        $token = $reuseOldToken ? $oldToken : bin2hex(random_bytes(32));

        if (! $reuseOldToken) {
            $request->session()->put(self::TOKEN_HASH_SESSION_KEY, hash('sha256', $token));
        }

        return view('admin.orders.create', [
            'creationToken' => $token,
            'shippingMethods' => ShippingMethod::query()->activeOrdered()->get(),
            'paymentMethods' => PaymentMethod::query()
                ->where('is_active', true)
                ->whereIn('code', self::SUPPORTED_PAYMENT_METHODS)
                ->whereIn('type', [
                    PaymentMethodType::Offline->value,
                    PaymentMethodType::ManualTransfer->value,
                ])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function summary(AdminOrderRequest $request): JsonResponse
    {
        $summary = $this->orders->summarize($request->validated());

        if (! $summary->isValid()) {
            return response()->json(['success' => false, 'errors' => $summary->errors], 422);
        }

        return response()->json([
            'success' => true,
            'summary' => $this->summaryData($summary),
        ]);
    }

    public function store(AdminOrderRequest $request): RedirectResponse
    {
        $token = (string) $request->validated('admin_creation_token');
        $tokenHash = hash('sha256', $token);
        $completed = (array) $request->session()->get(self::COMPLETED_SESSION_KEY, []);

        if (hash_equals((string) ($completed['token_hash'] ?? ''), $tokenHash)) {
            $order = Order::query()->find($completed['order_id'] ?? null);

            if ($order && hash_equals((string) $order->admin_creation_key, $tokenHash)) {
                return redirect()->route('admin.orders.show', $order);
            }
        }

        $expectedHash = (string) $request->session()->get(self::TOKEN_HASH_SESSION_KEY, '');

        if ($expectedHash === '' || ! hash_equals($expectedHash, $tokenHash)) {
            throw ValidationException::withMessages([
                'admin_creation_token' => 'The Order creation form has expired. Reload it and try again.',
            ]);
        }

        $order = $this->orders->create($request->validated(), $tokenHash);
        $request->session()->put(self::COMPLETED_SESSION_KEY, [
            'token_hash' => $tokenHash,
            'order_id' => $order->getKey(),
        ]);
        $request->session()->forget(self::TOKEN_HASH_SESSION_KEY);

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('success', 'Order created successfully.');
    }

    public function customers(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q'));
        $customers = User::query()
            ->customers()
            ->active()
            ->with(['customerAddresses' => fn ($query) => $query
                ->orderByDesc('is_default_shipping')
                ->orderByDesc('is_default_billing')
                ->orderBy('id')])
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->orderBy('id')
            ->limit(20)
            ->get();

        return response()->json(['results' => $customers->map(fn (User $customer): array => [
            'id' => $customer->getKey(),
            'text' => $customer->name.($customer->email ? " ({$customer->email})" : ''),
            'has_account' => $customer->has_account,
            'addresses' => $customer->customerAddresses->map(fn ($address): array => [
                'id' => $address->getKey(),
                'label' => $address->label ?: $address->address_line_1,
                'first_name' => $address->first_name,
                'last_name' => $address->last_name,
                'company' => $address->company,
                'phone' => $address->phone,
                'address_line_1' => $address->address_line_1,
                'address_line_2' => $address->address_line_2,
                'city' => $address->city,
                'state' => $address->state,
                'postal_code' => $address->postal_code,
                'country_code' => $address->country_code,
            ])->values()->all(),
        ])->values()]);
    }

    public function products(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q'));
        $locale = $this->orderLocale();
        $products = Product::query()
            ->active()
            ->visible()
            ->whereNull('configurable_id')
            ->whereIn('type', [ProductType::Simple->value, ProductType::Configurable->value])
            ->whereHas('translations', fn (Builder $query) => $query->where('locale', $locale))
            ->with([
                'translations' => fn ($query) => $query->where('locale', $locale),
                'inventory',
                'variants.inventory',
            ])
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search, $locale) {
                $query->where('sku', 'like', "%{$search}%")
                    ->orWhere('product_number', 'like', "%{$search}%")
                    ->orWhereHas('translations', fn (Builder $query) => $query
                        ->where('locale', $locale)
                        ->where('name', 'like', "%{$search}%"));
            }))
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->filter(fn (Product $product): bool => $product->type === ProductType::Configurable->value
                ? $product->variants->contains(fn (Product $variant): bool => $variant->status
                    && $variant->hasPositiveEffectivePrice())
                : $product->hasPositiveEffectivePrice());

        return response()->json(['results' => $products->map(fn (Product $product): array => [
            'id' => $product->getKey(),
            'text' => $product->translations->first()->name.' — '.$product->sku,
            'type' => $product->type,
            'sku' => $product->sku,
        ])->values()]);
    }

    public function configuration(Product $product): JsonResponse
    {
        abort_unless(
            $product->status
                && $product->is_visible_individually
                && $product->type === ProductType::Configurable->value
                && $product->configurable_id === null,
            404
        );
        $locale = $this->orderLocale();
        $product->load([
            'superAttributes.attribute.translations' => fn ($query) => $query->where('locale', $locale),
            'superAttributes.options.translations' => fn ($query) => $query->where('locale', $locale),
            'variants' => fn ($query) => $query
                ->active()
                ->where('type', ProductType::Simple->value)
                ->with(['inventory', 'attributeValues']),
        ]);
        $requiredAttributeIds = $product->superAttributes
            ->pluck('attribute_id')
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();
        $variants = $product->variants
            ->filter(function (Product $variant) use ($requiredAttributeIds): bool {
                $selectedAttributeIds = $variant->attributeValues
                    ->whereNotNull('attribute_option_id')
                    ->pluck('attribute_id')
                    ->map(fn ($id): int => (int) $id)
                    ->sort()
                    ->values()
                    ->all();

                return $variant->hasPositiveEffectivePrice()
                    && $requiredAttributeIds !== []
                    && $selectedAttributeIds === $requiredAttributeIds;
            })
            ->map(fn (Product $variant): array => [
                'id' => $variant->getKey(),
                'sku' => $variant->sku,
                'available_quantity' => $variant->inventory?->availableQuantity() ?? '0.0000',
                'options' => $variant->attributeValues
                    ->whereNotNull('attribute_option_id')
                    ->pluck('attribute_option_id', 'attribute_id')
                    ->map(fn ($id): int => (int) $id)
                    ->all(),
            ])->values();

        return response()->json([
            'product_id' => $product->getKey(),
            'attributes' => $product->superAttributes->map(fn ($super): array => [
                'id' => (int) $super->attribute_id,
                'name' => $super->attribute->translations->first()?->admin_name ?? $super->attribute->code,
                'options' => $super->options->map(fn ($option): array => [
                    'id' => $option->getKey(),
                    'label' => $option->translations->first()?->label ?? $option->code,
                ])->values()->all(),
            ])->values(),
            'variants' => $variants,
        ]);
    }

    private function summaryData($summary): array
    {
        return [
            'items' => $summary->items,
            'subtotal' => $summary->subtotal,
            'discount_total' => $summary->discountTotal,
            'tax_total' => $summary->taxTotal,
            'shipping_amount' => $summary->shippingAmount,
            'grand_total' => $summary->grandTotal,
            'formatted_subtotal' => format_store_price($summary->subtotal, $summary->currencyCode),
            'formatted_discount_total' => format_store_price($summary->discountTotal, $summary->currencyCode),
            'formatted_tax_total' => format_store_price($summary->taxTotal, $summary->currencyCode),
            'formatted_shipping_amount' => format_store_price($summary->shippingAmount, $summary->currencyCode),
            'formatted_grand_total' => format_store_price($summary->grandTotal, $summary->currencyCode),
        ];
    }

    private function orderLocale(): string
    {
        $locale = (string) setting('localization.default_locale', config('app.locale'));

        return in_array($locale, ['en', 'ar'], true) ? $locale : 'en';
    }
}
