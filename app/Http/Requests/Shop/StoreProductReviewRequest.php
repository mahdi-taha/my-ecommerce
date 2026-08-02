<?php

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('customer') !== null;
    }

    public function rules(): array
    {
        return ['rating' => ['required', 'integer', 'between:1,5'], 'title' => ['nullable', 'string', 'max:150'], 'review' => ['required', 'string', 'min:10', 'max:5000']];
    }
}
