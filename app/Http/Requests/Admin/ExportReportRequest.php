<?php

namespace App\Http\Requests\Admin;

class ExportReportRequest extends ReportRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'format' => ['nullable', 'in:csv']];
    }
}
