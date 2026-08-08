<?php

namespace App\Contracts\Reports;

use App\DTOs\Reports\ReportFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Traversable;

interface ReportQuery
{
    public function title(): string;

    /** @return array<string, string> */
    public function columns(): array;

    /** @return array<string, scalar> */
    public function summary(ReportFilters $filters): array;

    public function rows(ReportFilters $filters): LengthAwarePaginator;

    /** @return Traversable<int, array<string, scalar|null>> */
    public function exportRows(ReportFilters $filters): Traversable;
}
