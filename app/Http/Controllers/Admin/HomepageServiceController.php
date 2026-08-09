<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHomepageServiceRequest;
use App\Http\Requests\Admin\UpdateHomepageServiceRequest;
use App\Models\HomepageService;
use App\Services\HomepageServiceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class HomepageServiceController extends Controller
{
    public function __construct(private HomepageServiceService $services) {}

    public function index(Request $request): JsonResponse|View
    {
        if ($request->ajax()) {
            $query = HomepageService::query()
                ->with('translations');

            return DataTables::eloquent($query)
                ->addColumn('title', fn (HomepageService $service) => e($service->translations->firstWhere('locale', 'en')?->title))
                ->editColumn('icon', fn (HomepageService $service) => '<i class="'.e($service->icon->cssClass()).'" aria-hidden="true"></i> '.e($service->icon->label()))
                ->editColumn('is_active', fn (HomepageService $service) => $service->is_active
                    ? '<span class="badge bg-success">Active</span>'
                    : '<span class="badge bg-danger">Inactive</span>')
                ->addColumn('action', fn (HomepageService $service) => view('admin.homepage-services._actions', compact('service'))->render())
                ->filterColumn('title', fn ($query, string $keyword) => $query->whereHas(
                    'translations',
                    fn ($translationQuery) => $translationQuery->where('locale', 'en')->where('title', 'like', "%{$keyword}%")
                ))
                ->rawColumns(['icon', 'is_active', 'action'])
                ->toJson();
        }

        return view('admin.homepage-services.index', [
            'activeServiceCount' => HomepageService::query()->active()->count(),
            'maximumActiveServices' => HomepageServiceService::MAX_ACTIVE,
        ]);
    }

    public function create(): View
    {
        return view('admin.homepage-services.create');
    }

    public function store(StoreHomepageServiceRequest $request): RedirectResponse
    {
        $this->services->create($request->validated());

        return redirect()->route('admin.homepage-services.index')->with('success', 'Homepage service created.');
    }

    public function edit(HomepageService $homepageService): View
    {
        $homepageService->load('translations');

        return view('admin.homepage-services.edit', compact('homepageService'));
    }

    public function update(UpdateHomepageServiceRequest $request, HomepageService $homepageService): RedirectResponse
    {
        $this->services->update($homepageService, $request->validated());

        return back()->with('success', 'Homepage service updated.');
    }

    public function destroy(HomepageService $homepageService): RedirectResponse
    {
        $this->services->delete($homepageService);

        return redirect()->route('admin.homepage-services.index')->with('success', 'Homepage service deleted.');
    }
}
