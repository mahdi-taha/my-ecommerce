<?php

namespace App\Http\Requests\Admin;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShippingTreatment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'administrator_id' => ['nullable', 'integer', 'exists:users,id'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'order_status' => ['nullable', Rule::enum(OrderStatus::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'fulfillment_status' => ['nullable', Rule::enum(FulfillmentStatus::class)],
            'shipping_treatment' => ['nullable', Rule::enum(ShippingTreatment::class)],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
        ];
    }
}
