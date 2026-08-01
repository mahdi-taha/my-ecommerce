<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkUpdateVariantsRequest;
use App\Http\Requests\StoreVariantRequest;
use App\Http\Requests\UpdateVariantRequest;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class VariantController extends Controller
{
    public function __construct(private ProductService $productService) {}

    public function index(Request $request, Product $product): JsonResponse|View
    {
        $this->ensureConfigurableParent($product);

        if ($request->ajax()) {
            $variants = $product->variants()
                ->with([
                    'images' => function ($query) {
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

            if ($request->filled('status')) {
                $variants->where('status', $request->status);
            }

            $parentBaseImage = $product->images()
                ->where('is_base', true)
                ->first();

            return DataTables::eloquent($variants)
                ->addColumn('select', function (Product $variant) {
                    $inventory = $variant->inventory;
                    $data = [
                        'id' => $variant->id,
                        'combination' => $this->combinationLabel($variant),
                        'sku' => $variant->sku,
                        'price' => $variant->price,
                        'special_price' => $variant->special_price,
                        'special_price_from' => $variant->special_price_from?->format('Y-m-d\TH:i'),
                        'special_price_to' => $variant->special_price_to?->format('Y-m-d\TH:i'),
                        'quantity' => $inventory?->quantity ?? 0,
                        'status' => $variant->status,
                    ];

                    return '<input type="checkbox" class="form-check-input variant-checkbox" value="'.$variant->id.'" data-variant="'.e(json_encode($data)).'">';
                })
                ->addColumn('image', function (Product $variant) use ($parentBaseImage) {
                    $image = $variant->images->first() ?? $parentBaseImage;

                    if (! $image) {
                        return '<span class="text-muted">No Image</span>';
                    }

                    return '<img src="'.e(Storage::disk('public')->url($image->path)).'" alt="" width="50" height="50" class="rounded object-fit-cover">';
                })
                ->addColumn('combination', fn (Product $variant) => $this->combinationLabel($variant))
                ->filterColumn('combination', function ($query, $keyword) {
                    $query->whereHas('attributeValues.option.translations', function ($query) use ($keyword) {
                        $query->where('locale', 'en')
                            ->where('label', 'like', "%{$keyword}%");
                    });
                })
                ->editColumn('price', fn (Product $variant) => number_format((float) $variant->price, 2))
                ->addColumn('quantity', fn (Product $variant) => $variant->inventory?->quantity ?? 0)
                ->editColumn('status', function (Product $variant) {
                    return $variant->status
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-danger">Inactive</span>';
                })
                ->addColumn('action', function (Product $variant) use ($product) {
                    $url = route('admin.products.variants.edit', [$product, $variant]);

                    return '<a href="'.$url.'" class="btn text-primary p-0" title="Edit"><i class="ti ti-edit fs-6"></i></a>';
                })
                ->rawColumns(['select', 'image', 'status', 'action'])
                ->toJson();
        }

        $product->load([
            'translations',
            'images',
            'superAttributes.attribute.translations' => function ($query) {
                $query->where('locale', 'en');
            },
            'superAttributes.attribute.options.translations' => function ($query) {
                $query->where('locale', 'en');
            },
        ])->loadCount('variants');

        return view('admin.products.variants.index', compact('product'));
    }

    public function store(StoreVariantRequest $request, Product $product): RedirectResponse
    {
        $this->ensureConfigurableParent($product);
        $this->productService->createMissingVariant(
            $product,
            $request->validated()['options']
        );

        return redirect()
            ->route('admin.products.variants.index', $product)
            ->with('success', 'Variant created successfully.');
    }

    public function bulkUpdate(
        BulkUpdateVariantsRequest $request,
        Product $product
    ): RedirectResponse {
        $this->ensureConfigurableParent($product);
        $this->productService->bulkUpdateVariants(
            $product,
            $request->validated()
        );

        return redirect()
            ->route('admin.products.variants.index', $product)
            ->with('success', 'Selected variants updated successfully.');
    }

    public function edit(Product $product, Product $variant): View
    {
        $this->ensureVariantBelongsToProduct($product, $variant);

        $variant->load([
            'inventory',
            'images',
            'attributeValues.attribute.translations',
            'attributeValues.option.translations',
        ]);
        $product->load(['translations', 'images']);

        return view(
            'admin.products.variants.edit',
            compact('product', 'variant')
        );
    }

    public function update(
        UpdateVariantRequest $request,
        Product $product,
        Product $variant
    ): RedirectResponse {
        $this->ensureVariantBelongsToProduct($product, $variant);

        $this->productService->updateVariant(
            $variant,
            $request->validated()
        );

        return redirect()
            ->route('admin.products.variants.index', $product)
            ->with('success', 'Variant updated successfully.');
    }

    private function ensureVariantBelongsToProduct(
        Product $product,
        Product $variant
    ): void {
        $this->ensureConfigurableParent($product);

        abort_unless(
            $variant->type === 'simple' &&
            $variant->configurable_id === $product->id,
            404
        );
    }

    private function ensureConfigurableParent(Product $product): void
    {
        abort_unless(
            $product->type === 'configurable' &&
            $product->configurable_id === null,
            404
        );
    }

    private function combinationLabel(Product $variant): string
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
