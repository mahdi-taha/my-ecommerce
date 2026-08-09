<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHomepageBannerRequest;
use App\Http\Requests\Admin\UpdateHomepageBannerRequest;
use App\Models\HomepageBanner;
use App\Services\HomepageBannerService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class HomepageBannerController extends Controller
{
    public function __construct(private HomepageBannerService $banners) {}

    public function index(Request $request): JsonResponse|View
    {
        if ($request->ajax()) {
            return DataTables::eloquent(HomepageBanner::query()->with('translations'))
                ->addColumn('title', fn ($banner) => e($banner->translations->firstWhere('locale', 'en')?->title))
                ->editColumn('placement', fn ($banner) => $banner->placement->value)
                ->editColumn('is_active', fn ($banner) => $banner->is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>')
                ->addColumn('action', fn ($banner) => '<a class="btn text-primary" href="'.e(route('admin.homepage-banners.edit', $banner)).'"><i class="ti ti-edit fs-6"></i></a>')
                ->rawColumns(['is_active', 'action'])
                ->toJson();
        }

        return view('admin.homepage-banners.index');
    }

    public function create(): View
    {
        return view('admin.homepage-banners.create');
    }

    public function store(StoreHomepageBannerRequest $request): RedirectResponse
    {
        $this->banners->create($request->validated(), $request->file('image'));

        return redirect()->route('admin.homepage-banners.index')->with('success', 'Homepage content created.');
    }

    public function edit(HomepageBanner $homepageBanner): View
    {
        $homepageBanner->load('translations');

        return view('admin.homepage-banners.edit', compact('homepageBanner'));
    }

    public function update(UpdateHomepageBannerRequest $request, HomepageBanner $homepageBanner): RedirectResponse
    {
        $this->banners->update($homepageBanner, $request->validated(), $request->file('image'));

        return back()->with('success', 'Homepage content updated.');
    }

    public function destroy(HomepageBanner $homepageBanner): RedirectResponse
    {
        $this->banners->delete($homepageBanner);

        return redirect()->route('admin.homepage-banners.index')->with('success', 'Homepage content deleted.');
    }
}
