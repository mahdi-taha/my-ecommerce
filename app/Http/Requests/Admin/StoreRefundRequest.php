<?php

namespace App\Http\Requests\Admin;

use App\Enums\ShippingTreatment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRefundRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []));
        if (! $items->contains(fn ($item) => is_array($item) && array_key_exists('selected', $item))) {
            return;
        }

        $this->merge([
            'items' => $items
                ->filter(fn ($item) => is_array($item) && ! empty($item['selected']))
                ->map(fn (array $item) => collect($item)->except('selected')->all())
                ->values()->all(),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        $decimal = ['required', 'regex:/^(?:0|[1-9]\d{0,10})(?:\.\d{1,4})?$/', 'decimal:0,4', 'max:99999999999.9999'];

        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'idempotency_key' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.order_item_id' => ['required', 'integer', 'distinct', 'exists:order_items,id'],
            'items.*.quantity' => $decimal,
            'return_shipping_cost' => $decimal,
            'shipping_treatment' => ['required', Rule::enum(ShippingTreatment::class)],
            'reason' => ['nullable', 'string', 'max:500'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'order_id' => 'order',
            'items' => 'refund items',
            'items.*.order_item_id' => 'selected refund item',
            'items.*.quantity' => 'refund quantity',
            'return_shipping_cost' => 'return shipping cost',
            'shipping_treatment' => 'shipping treatment',
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required' => 'Please select an order.',
            'order_id.integer' => 'Please select a valid order.',
            'order_id.exists' => 'Please select a valid order.',
            'items.required' => 'Please select at least one item to refund.',
            'items.array' => 'Please select at least one item to refund.',
            'items.min' => 'Please select at least one item to refund.',
            'items.max' => 'You may refund up to 100 items at a time.',
            'items.*.order_item_id.required' => 'A selected refund item is invalid.',
            'items.*.order_item_id.integer' => 'A selected refund item is invalid.',
            'items.*.order_item_id.distinct' => 'Each refund item may only be selected once.',
            'items.*.order_item_id.exists' => 'A selected refund item is invalid.',
            'items.*.quantity.required' => 'Please enter a refund quantity.',
            'items.*.quantity.regex' => 'Please enter a valid refund quantity with up to 4 decimal places.',
            'items.*.quantity.decimal' => 'Please enter a valid refund quantity with up to 4 decimal places.',
            'items.*.quantity.max' => 'The refund quantity is too large.',
            'return_shipping_cost.required' => 'Please enter the return shipping cost.',
            'return_shipping_cost.regex' => 'Please enter a valid return shipping cost with up to 4 decimal places.',
            'return_shipping_cost.decimal' => 'Please enter a valid return shipping cost with up to 4 decimal places.',
            'return_shipping_cost.max' => 'The return shipping cost is too large.',
            'shipping_treatment.required' => 'Please choose how return shipping should be handled.',
            'shipping_treatment.enum' => 'Please choose a valid return shipping treatment.',
        ];
    }
}
