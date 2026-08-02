<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ApproveOrderCancellationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $adminNote = trim((string) $this->input('admin_note'));

        $this->merge(['admin_note' => $adminNote === '' ? null : $adminNote]);
    }

    public function rules(): array
    {
        return ['admin_note' => ['nullable', 'string', 'max:2000']];
    }
}
