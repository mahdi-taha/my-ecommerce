<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCategoryRequest;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class CategoryController extends Controller
{
    public function __construct(private CategoryService $categoryService) {}

    public function index(Request $request): JsonResponse|View
    {
        if ($request->ajax()) {
            $data = Category::query()->with([
                'translations' => fn ($query) => $query->where('locale', 'en'),
                'parent.translations' => fn ($query) => $query->where('locale', 'en'),
            ]);

            return DataTables::eloquent($data)
                ->addColumn('name', fn (Category $category) => $category->translations->first()?->name ?? '-')
                ->addColumn('parent_name', fn (Category $category) => $category->parent?->translations->first()?->name ?? '-')
                ->filterColumn('name', fn ($query, $keyword) => $query->whereHas('translations', fn ($query) => $query->where('locale', 'en')->where('name', 'like', "%{$keyword}%")))
                ->orderColumn('name', fn ($query, $order) => $query->orderBy(CategoryTranslation::select('name')->whereColumn('category_id', 'categories.id')->where('locale', 'en')->limit(1), $order))
                ->filterColumn('parent_name', fn ($query, $keyword) => $query->whereHas('parent.translations', fn ($query) => $query->where('locale', 'en')->where('name', 'like', "%{$keyword}%")))
                ->orderColumn('parent_name', fn ($query, $order) => $query->orderBy(CategoryTranslation::select('name')->whereColumn('category_id', 'categories.parent_id')->where('locale', 'en')->limit(1), $order))
                ->editColumn('status', fn (Category $category) => $category->status ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>')
                ->addColumn('action', fn (Category $category) => '
                    <span class="d-flex gap-2">
                        <a href="'.e(route('admin.categories.edit', $category)).'" class="btn text-primary p-0" title="Edit">
                            <i class="ti ti-edit fs-6"></i>
                        </a>
                        <button type="button" class="btn text-danger p-0 category-delete"
                            data-url="'.e(route('admin.categories.destroy', $category)).'"
                            data-name="'.e($category->translations->first()?->name ?? 'Category').'" title="Delete">
                            <i class="ti ti-trash fs-6"></i>
                        </button>
                    </span>')
                ->addIndexColumn()->rawColumns(['status', 'action'])->toJson();
        }

        return view('admin.categories.index');
    }

    public function create(): View
    {
        return view('admin.categories.create', $this->formData());
    }

    public function store(SaveCategoryRequest $request): RedirectResponse
    {
        $this->categoryService->create($request->validated());

        return redirect()->route('admin.categories.index')->with('success', 'Category created successfully.');
    }

    public function edit(Category $category): View
    {
        $category->load(['translations', 'filterableAttributes']);
        $excluded = array_merge([$category->id], $this->categoryService->descendantIds($category));

        return view('admin.categories.edit', array_merge($this->formData($excluded), compact('category')));
    }

    public function update(SaveCategoryRequest $request, Category $category): RedirectResponse
    {
        $this->categoryService->update($category, $request->validated());

        return redirect()->route('admin.categories.index')->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->categoryService->delete($category);

        return response()->json([
            'message' => 'Category deleted successfully.',
        ]);
    }

    private function formData(array $excluded = []): array
    {
        $categories = Category::with(['translations' => fn ($query) => $query->where('locale', 'en')])
            ->when($excluded !== [], fn ($query) => $query->whereNotIn('id', $excluded))
            ->orderBy('level')->orderBy('position')->get();
        $attributes = Attribute::with(['translations' => fn ($query) => $query->where('locale', 'en')])
            ->where('is_filterable', true)->orderBy('sort_order')->get();

        return compact('categories', 'attributes');
    }
}
