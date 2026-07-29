<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateCouponRequest extends StoreCouponRequest
{
    protected function codeRules(): array
    {
        return [
            'required',
            'string',
            'max:100',
            'regex:/^[A-Z0-9_-]+$/',
            Rule::unique('coupons', 'code')->ignore($this->route('coupon')),
        ];
    }
}
