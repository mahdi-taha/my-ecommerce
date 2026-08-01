<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'notification_rules' => $this->input('notification_rules', []),
        ]);
    }

    public function rules(): array
    {
        return [
            'store_name' => ['required', 'string', 'max:255'],
            'store_email' => ['nullable', 'email', 'max:255'],
            'store_phone' => ['nullable', 'string', 'max:50'],
            'store_address' => ['nullable', 'string'],
            'store_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'facebook_url' => ['nullable', 'url:http,https', 'max:2048'],
            'whatsapp_url' => ['nullable', 'url:http,https', 'max:2048'],
            'instagram_url' => ['nullable', 'url:http,https', 'max:2048'],
            'default_locale' => ['required', Rule::in(['en', 'ar'])],
            'timezone' => ['required', 'string', 'max:100'],
            'default_currency' => ['required', Rule::in(['USD', 'LBP'])],
            'tax_mode' => ['required', Rule::in(['b2b', 'b2c'])],
            'default_tax_id' => [
                'nullable',
                Rule::exists('taxes', 'id')->where(fn ($query) => $query->where('status', true)),
            ],
            'allow_guest_checkout' => ['nullable', 'boolean'],
            'manual_whatsapp_number' => ['nullable', 'string', 'max:50'],
            'manual_wallet_title' => ['nullable', 'string', 'max:255'],
            'manual_wallet_name' => ['nullable', 'string', 'max:255'],
            'manual_wallet_number' => ['nullable', 'string', 'max:255'],
            'manual_wallet_instructions' => ['nullable', 'string', 'max:2000'],
            'manual_bank_title' => ['nullable', 'string', 'max:255'],
            'manual_bank_name' => ['nullable', 'string', 'max:255'],
            'manual_bank_account_name' => ['nullable', 'string', 'max:255'],
            'manual_bank_account_number' => ['nullable', 'string', 'max:255'],
            'manual_bank_iban' => ['nullable', 'string', 'max:255'],
            'manual_bank_swift' => ['nullable', 'string', 'max:255'],
            'manual_bank_instructions' => ['nullable', 'string', 'max:2000'],
            'notification_rules' => ['array'],
            'notification_rules.*' => [
                'integer',
                'distinct',
                Rule::exists('notification_rules', 'id'),
            ],
        ];
    }
}
