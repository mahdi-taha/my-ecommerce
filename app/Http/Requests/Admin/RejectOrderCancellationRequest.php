<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RejectOrderCancellationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['admin_note' => trim((string) $this->input('admin_note'))]);
    }

    public function rules(): array
    {
        return ['admin_note' => ['required', 'string', 'max:2000']];
    }
}
