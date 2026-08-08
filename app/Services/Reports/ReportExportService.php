<?php

namespace App\Services\Reports;

use App\Contracts\Reports\ReportQuery;
use App\DTOs\Reports\ReportFilters;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    public function csv(string $name, ReportQuery $report, ReportFilters $filters): StreamedResponse
    {
        return response()->streamDownload(function () use ($report, $filters): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_values($report->columns()));
            foreach ($report->exportRows($filters) as $row) {
                fputcsv($output, array_map(fn ($key) => $this->safe(data_get($row, $key)), array_keys($report->columns())));
            }
            fclose($output);
        }, $name.'-report-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function safe(mixed $value): string|int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/u', $value) ? "'".$value : $value;
    }
}
