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
}
