<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfigureProductRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tax;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;

class ProductController extends Controller
{
    public function __construct(private ProductService $productService) {}

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Product::query()
                ->with([
                    'translations' => function ($query) {
                        $query->where('locale', 'en');
                    },
                    'images' => function ($query) {
                        $query->where('is_base', true);
                    },
                    'configurable.translations' => function ($query) {
                        $query->where('locale', 'en');
                    },
                    'configurable.images' => function ($query) {
                        $query->where('is_base', true);
                    },
                    'inventory',
                    'attributeValues.attribute.translations' => function ($query) {
                        $query->where('locale', 'en');
                    },
                    'attributeValues.option.translations' => function ($query) {
                        $query->where('locale', 'en');
                    },
                ]);

            if ($request->filled('type')) {
                match ($request->type) {
                    'standalone_simple' => $data
                        ->where('type', 'simple')
                        ->whereNull('configurable_id'),
                    'variant' => $data->whereNotNull('configurable_id'),
                    'configurable' => $data
                        ->where('type', 'configurable')
                        ->whereNull('configurable_id'),
                    default => null,
                };
            }

            if ($request->filled('status')) {
                $data->where('status', $request->status);
            }

            return DataTables::eloquent($data)
                ->addColumn('image', function (Product $product) {
                    $image = $product->images->first()
                        ?? $product->configurable?->images->first();

                    if (! $image) {
                        return '<span class="text-muted">No Image</span>';
                    }

                    $url = Storage::disk('public')->url($image->path);

                    return '<img src="'.e($url).'" alt="" width="50" height="50" class="rounded object-fit-cover">';
                })
                ->addColumn('generated_name', function (Product $product) {
                    if ($product->configurable_id !== null) {
                        $parentName = $product->configurable?->translations->first()?->name
                            ?? 'Configurable Product';

                        return $parentName.' — '.$this->variantCombinationLabel($product);
                    }

                    return $product->translations->first()?->name ?? '-';
                })
                ->filterColumn('generated_name', function ($query, $keyword) {
                    $query->where(function ($query) use ($keyword) {
                        $query->whereHas('translations', function ($query) use ($keyword) {
                            $query->where('locale', 'en')
                                ->where('name', 'like', "%{$keyword}%");
                        })->orWhereHas('configurable.translations', function ($query) use ($keyword) {
                            $query->where('locale', 'en')
                                ->where('name', 'like', "%{$keyword}%");
                        })->orWhereHas('attributeValues.option.translations', function ($query) use ($keyword) {
                            $query->where('locale', 'en')
                                ->where('label', 'like', "%{$keyword}%");
                        });
                    });
                })
                ->editColumn('type', function (Product $product) {
                    if ($product->configurable_id !== null) {
                        return 'Variant';
                    }

                    return match ($product->type) {
                        'simple' => 'Standalone Simple',
                        'configurable' => 'Configurable Parent',
                        default => ucfirst($product->type),
                    };
                })
                ->addColumn('parent_name', function (Product $product) {
                    return $product->configurable?->translations->first()?->name ?? '-';
                })
                ->filterColumn('parent_name', function ($query, $keyword) {
                    $query->whereHas('configurable.translations', function ($query) use ($keyword) {
                        $query->where('locale', 'en')
                            ->where('name', 'like', "%{$keyword}%");
                    });
                })
                ->editColumn('price', function (Product $product) {
                    return number_format((float) $product->price, 2);
                })
                ->editColumn('status', function (Product $product) {
                    return $product->status
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-danger">Inactive</span>';
                })
                ->addColumn('quantity', function (Product $product) {
                    return $product->inventory?->quantity ?? '-';
                })
                ->addColumn('action', function (Product $product) {
                    $editUrl = $product->configurable_id !== null
                        ? route('admin.products.variants.edit', [$product->configurable_id, $product])
                        : route('admin.products.edit', $product);
                    $deleteUrl = route('admin.products.destroy', $product);

                    return '
                        <span class="d-flex gap-2">
                            <a href="'.e($editUrl).'" class="btn text-primary p-0" title="Edit">
                                <i class="ti ti-edit fs-6"></i>
                            </a>
                            <button type="button" class="btn text-danger p-0 product-delete"
                                data-url="'.e($deleteUrl).'" data-sku="'.e($product->sku).'" title="Delete">
                                <i class="ti ti-trash fs-6"></i>
                            </button>
                        </span>
                    ';
                })
                ->rawColumns(['image', 'status', 'action'])
                ->toJson();
        }

        return view('admin.products.index');
    }

    public function create()
    {
        return view('admin.products.create');
    }

    public function store(StoreProductRequest $request)
    {
        $product = $this->productService->create($request->validated());

        if ($product->type === 'configurable') {
            return redirect()
                ->route('admin.products.configure', $product);
        }

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('success', 'Product created successfully.');
    }

    public function edit(Product $product)
    {
        abort_if($product->configurable_id !== null, 404);

        $product->load([
            'translations',
            'inventory',
            'categories',
            'images',
            'attributeValues',
            'superAttributes',
            'relatedProducts',
        ]);

        $categories = collect();
        $attributes = collect();
        $taxes = collect();
        $relatedProductOptions = collect();
        $selectedRelatedProductIds = [];

        if (in_array($product->type, ['simple', 'configurable'])) {
            $categories = Category::with([
                'translations' => function ($query) {
                    $query->where('locale', 'en');
                },
            ])
                ->orderBy('level')
                ->orderBy('position')
                ->get();

            $categoryPaths = $this->categoryPaths($categories);

            $categories->each(function (Category $category) use ($categoryPaths) {
                $category->setAttribute(
                    'display_path',
                    $categoryPaths[$category->id]
                );
            });

            $attributes = Attribute::with([
                'translations' => function ($query) {
                    $query->where('locale', 'en');
                },
                'options.translations' => function ($query) {
                    $query->where('locale', 'en');
                },
            ])
                ->where('is_active', true)
                ->when(
                    $product->type !== 'simple',
                    fn ($query) => $query->where('is_configurable', false)
                )
                ->orderBy('sort_order')
                ->get();
        }

        if ($product->type === 'configurable') {
            $product->load([
                'superAttributes.attribute.translations',
                'superAttributes.attribute.options.translations',
            ]);
            $product->loadCount('variants');
        } elseif ($product->type === 'simple') {
            $taxes = Tax::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'rate']);

            $selectedRelatedProductIds = $product->relatedProducts
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();
            $selectedOrder = array_flip($selectedRelatedProductIds);

            $relatedProductOptions = Product::query()
                ->active()
                ->visible()
                ->where('type', 'simple')
                ->whereNull('configurable_id')
                ->whereKeyNot($product->getKey())
                ->whereHas('translations', fn ($query) => $query
                    ->where('locale', 'en'))
                ->with([
                    'translations' => fn ($query) => $query
                        ->where('locale', 'en'),
                ])
                ->orderBy('sku')
                ->get()
                ->sortBy(fn (Product $option) => [
                    $selectedOrder[$option->getKey()] ?? PHP_INT_MAX,
                    $option->sku,
                ])
                ->values();
        }

        return view(
            'admin.products.edit',
            compact(
                'product',
                'categories',
                'attributes',
                'taxes',
                'relatedProductOptions',
                'selectedRelatedProductIds'
            )
        );
    }

    public function update(
        UpdateProductRequest $request,
        Product $product
    ) {
        abort_if($product->configurable_id !== null, 404);

        $this->productService->update(
            $product,
            $request->validated()
        );

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->productService->delete($product);

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }

    public function configure(Product $product)
    {
        abort_unless(
            $product->type === 'configurable' &&
            $product->configurable_id === null,
            404
        );

        if ($product->superAttributes()->exists() || $product->variants()->exists()) {
            return redirect()
                ->route('admin.products.edit', $product)
                ->with('info', 'This configurable product has already been configured.');
        }

        $attributes = Attribute::with([
            'translations' => function ($query) {
                $query->where('locale', 'en');
            },
            'options.translations' => function ($query) {
                $query->where('locale', 'en');
            },
        ])
            ->where('is_active', true)
            ->where('type', 'select')
            ->where('is_configurable', true)
            ->orderBy('sort_order')
            ->get();

        return view(
            'admin.products.configure',
            compact('product', 'attributes')
        );
    }

    public function generateConfiguration(
        ConfigureProductRequest $request,
        Product $product
    ) {
        abort_unless(
            $product->type === 'configurable' &&
            $product->configurable_id === null,
            404
        );

        $this->productService->generateVariants(
            $product,
            $request->validated()['super_attributes']
        );

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('success', 'Product variants generated successfully.');
    }

    private function categoryPaths($categories): array
    {
        $categoriesById = $categories->keyBy('id');
        $paths = [];

        foreach ($categories as $category) {
            $names = [];
            $current = $category;
            $visitedIds = [];

            while ($current && ! in_array($current->id, $visitedIds, true)) {
                $visitedIds[] = $current->id;
                array_unshift(
                    $names,
                    $current->translations->first()?->name ?? 'Category #'.$current->id
                );
                $current = $current->parent_id
                    ? $categoriesById->get($current->parent_id)
                    : null;
            }

            $paths[$category->id] = implode(' / ', $names);
        }

        return $paths;
    }

    private function variantCombinationLabel(Product $variant): string
    {
        return $variant->attributeValues
            ->sortBy('attribute_id')
            ->map(function ($value) {
                $attribute = $value->attribute?->translations->first()?->admin_name
                    ?? $value->attribute?->code
                    ?? 'Attribute';
                $option = $value->option?->translations->first()?->label
                    ?? 'Option #'.$value->attribute_option_id;

                return $attribute.': '.$option;
            })
            ->implode(' / ');
    }
}
