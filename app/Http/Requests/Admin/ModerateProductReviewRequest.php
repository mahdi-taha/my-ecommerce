<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductReviewStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModerateProductReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([ProductReviewStatus::Approved->value, ProductReviewStatus::Rejected->value])],
            'admin_note' => [Rule::requiredIf($this->input('status') === ProductReviewStatus::Rejected->value), 'nullable', 'string', 'max:2000'],
        ];
    }
}
