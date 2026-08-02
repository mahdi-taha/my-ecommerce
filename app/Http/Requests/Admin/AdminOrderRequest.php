<?php

namespace App\Http\Requests\Admin;

use App\Enums\CartItemType;
use App\Enums\PaymentMethodType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminOrderRequest extends FormRequest
{
    private const SUPPORTED_PAYMENT_METHODS = [
        'cash_on_delivery',
        'manual_wallet_transfer',
        'manual_bank_transfer',
    ];

    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item): array {
                $options = collect($item['options'] ?? [])
                    ->mapWithKeys(fn ($value, $key) => [(string) $key => $value])
                    ->all();

                return [
                    'product_id' => $item['product_id'] ?? null,
                    'parent_product_id' => $item['parent_product_id'] ?? null,
                    'product_type' => is_string($item['product_type'] ?? null)
                        ? trim($item['product_type'])
                        : ($item['product_type'] ?? null),
                    'quantity' => $item['quantity'] ?? null,
                    'options' => $options,
                ];
            })
            ->values()
            ->all();

        $manualAddress = collect($this->input('manual_address', []))
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->all();

        $this->merge([
            'address_source' => $this->trimmed('address_source'),
            'shipping_method' => $this->trimmed('shipping_method'),
            'payment_method' => $this->trimmed('payment_method'),
            'admin_creation_token' => $this->trimmed('admin_creation_token'),
            'manual_address' => $manualAddress,
            'items' => $items,
        ]);
    }

    public function rules(): array
    {
        if ($this->routeIs('admin.orders.store') && $this->isCompletedReplay()) {
            return [
                'admin_creation_token' => ['required', 'string', 'size:64'],
            ];
        }

        $manual = fn () => $this->input('address_source') === 'manual';
        $saved = fn () => $this->input('address_source') === 'saved';

        return [
            'customer_id' => ['required', 'integer', Rule::exists('users', 'id')->where(
                fn ($query) => $query->where('account_type', 'customer')->where('is_active', true)
            )],
            'address_source' => ['required', Rule::in(['saved', 'manual'])],
            'saved_address_id' => [Rule::requiredIf($saved), Rule::prohibitedIf($manual), 'integer'],
            'manual_address' => [Rule::requiredIf($manual), Rule::prohibitedIf($saved), 'array'],
            'manual_address.first_name' => [Rule::requiredIf($manual), 'string', 'max:255'],
            'manual_address.last_name' => [Rule::requiredIf($manual), 'string', 'max:255'],
            'manual_address.company' => ['nullable', 'string', 'max:255'],
            'manual_address.email' => ['nullable', 'email', 'max:255'],
            'manual_address.phone' => ['nullable', 'string', 'max:255'],
            'manual_address.address_line_1' => [Rule::requiredIf($manual), 'string', 'max:255'],
            'manual_address.address_line_2' => ['nullable', 'string', 'max:255'],
            'manual_address.city' => [Rule::requiredIf($manual), 'string', 'max:255'],
            'manual_address.state' => ['nullable', 'string', 'max:255'],
            'manual_address.postal_code' => ['nullable', 'string', 'max:255'],
            'manual_address.country_code' => [Rule::requiredIf($manual), 'string', 'size:2', 'alpha'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.parent_product_id' => ['nullable', 'integer'],
            'items.*.product_type' => ['required', Rule::enum(CartItemType::class)],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.options' => ['array'],
            'items.*.options.*' => ['integer', 'min:1'],
            'shipping_method' => [
                'required',
                'string',
                Rule::exists('shipping_methods', 'code')->where('is_active', true),
            ],
            'payment_method' => [
                'required',
                'string',
                Rule::exists('payment_methods', 'code')->where(
                    fn ($query) => $query
                        ->where('is_active', true)
                        ->whereIn('code', self::SUPPORTED_PAYMENT_METHODS)
                        ->whereIn('type', [
                            PaymentMethodType::Offline->value,
                            PaymentMethodType::ManualTransfer->value,
                        ])
                ),
            ],
            'admin_creation_token' => [
                Rule::requiredIf(fn () => $this->routeIs('admin.orders.store')),
                'nullable',
                'string',
                'size:64',
            ],
        ];
    }

    private function trimmed(string $key): mixed
    {
        $value = $this->input($key);

        return is_string($value) ? trim($value) : $value;
    }

    private function isCompletedReplay(): bool
    {
        $token = $this->input('admin_creation_token');
        $completed = (array) $this->session()->get('admin_order_creation.completed', []);

        return is_string($token)
            && strlen($token) === 64
            && isset($completed['token_hash'])
            && hash_equals((string) $completed['token_hash'], hash('sha256', $token));
    }
}
