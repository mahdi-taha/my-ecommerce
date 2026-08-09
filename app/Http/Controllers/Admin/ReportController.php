<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExportReportRequest;
use App\Http\Requests\Admin\ReportRequest;
use App\Services\Reports\ReportExportService;
use App\Services\Reports\ReportFilterFactory;
use App\Services\Reports\ReportFilterOptions;
use App\Services\Reports\ReportRegistry;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private ReportRegistry $reports,
        private ReportFilterFactory $filters,
        private ReportFilterOptions $options,
        private ReportExportService $exports,
    ) {}

    public function index(): View
    {
        return view('admin.reports.index', ['reports' => $this->reports->names()]);
    }

    public function show(ReportRequest $request, string $report): View
    {
        $query = $this->reports->get($report);
        $availableFilters = $this->reports->filters($report);
        $filters = $this->filters->make($request->validated(), $availableFilters);

        return view('admin.reports.show', [
            'reportName' => $report,
            'report' => $query,
            'filters' => $filters,
            'availableFilters' => $availableFilters,
            'filterOptions' => $this->options->for($filters, $availableFilters),
            'summary' => $query->summary($filters),
            'rows' => $query->rows($filters),
        ]);
    }

    public function export(ExportReportRequest $request, string $report): StreamedResponse
    {
        $query = $this->reports->get($report);
        $availableFilters = $this->reports->filters($report);

        return $this->exports->csv($report, $query, $this->filters->make($request->validated(), $availableFilters));
    }
}
