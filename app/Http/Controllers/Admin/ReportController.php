<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportRequest;
use App\Services\Reports\ReportFilterFactory;
use App\Services\Reports\ReportRegistry;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private ReportRegistry $reports, private ReportFilterFactory $filters) {}

    public function index(): View
    {
        return view('admin.reports.index', ['reports' => $this->reports->names()]);
    }

    public function show(ReportRequest $request, string $report): View
    {
        $query = $this->reports->get($report);
        $filters = $this->filters->make($request->validated());

        return view('admin.reports.show', [
            'reportName' => $report,
            'report' => $query,
            'filters' => $filters,
            'summary' => $query->summary($filters),
            'rows' => $query->rows($filters),
        ]);
    }
}
