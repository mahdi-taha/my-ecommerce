<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCmsPageRequest;
use App\Models\CmsPage;
use App\Services\CmsPageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class CmsPageController extends Controller
{
    public function __construct(private CmsPageService $pages) {}

    public function index(Request $request): JsonResponse|View
    {
        if ($request->ajax()) {
            return DataTables::eloquent(CmsPage::query()->with('translations'))
                ->addColumn('title', fn (CmsPage $page) => e($page->translations->firstWhere('locale', 'en')?->title))
                ->editColumn('is_active', fn (CmsPage $page) => $page->is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>')
                ->editColumn('updated_at', fn (CmsPage $page) => $page->updated_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'))
                ->addColumn('action', fn (CmsPage $page) => '<a class="btn text-primary" href="'.e(route('admin.cms-pages.edit', $page)).'"><i class="ti ti-edit fs-6"></i></a>')
                ->rawColumns(['is_active', 'action'])->toJson();
        }

        return view('admin.cms-pages.index');
    }

    public function edit(CmsPage $cmsPage): View
    {
        $cmsPage->load('translations');

        return view('admin.cms-pages.edit', compact('cmsPage'));
    }

    public function update(UpdateCmsPageRequest $request, CmsPage $cmsPage): RedirectResponse
    {
        $this->pages->update($cmsPage, $request->validated());

        return back()->with('success', 'CMS page updated successfully.');
    }
}
