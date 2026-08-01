<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryAdjustmentRequest;
use App\Http\Requests\StoreInventoryReceiptRequest;
use App\Http\Requests\StoreOpeningStockRequest;
use App\Http\Requests\StoreStockCountRequest;
use App\Http\Requests\UpdateLowStockAlertRequest;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    public function index(Request $request): JsonResponse|View
    {
        if ($request->ajax()) {
            $products = $this->inventoryProductsQuery();

            return DataTables::eloquent($products)
                ->addColumn('name', fn (Product $product) => $this->productName($product))
                ->filterColumn('name', function ($query, $keyword) {
                    $query->where(function ($query) use ($keyword) {
                        $query->whereHas('translations', function ($query) use ($keyword) {
                            $query->where('locale', 'en')->where('name', 'like', "%{$keyword}%");
                        })->orWhereHas('configurable.translations', function ($query) use ($keyword) {
                            $query->where('locale', 'en')->where('name', 'like', "%{$keyword}%");
                        });
                    });
                })
                ->addColumn('product_type', fn (Product $product) => $product->configurable_id ? 'Variant' : 'Simple')
                ->addColumn('quantity', fn (Product $product) => $product->inventory?->quantity ?? '0.0000')
                ->addColumn('available_quantity', fn (Product $product) => $product->inventory?->availableQuantity() ?? '0.0000')
                ->addColumn('average_cost', fn (Product $product) => $product->inventory?->average_cost ?? '0.0000')
                ->addColumn('low_stock_alert', fn (Product $product) => $product->inventory?->low_stock_alert)
                ->addColumn('action', function (Product $product) {
                    $threshold = e($this->formatNumber($product->inventory?->low_stock_alert));

                    return '<div class="d-flex align-items-center gap-2 flex-wrap">
                        <a href="'.route('admin.inventory.receive', ['product' => $product->id]).'" class="btn text-primary p-0" title="Receive Stock"><i class="ti ti-package-import fs-6"></i></a>
                        <a href="'.route('admin.inventory.adjustment', ['product' => $product->id]).'" class="btn text-primary p-0 me-2" title="Adjust Stock"><i class="ti ti-adjustments fs-6"></i></a>
                        <a href="'.route('admin.inventory.history', ['product_id' => $product->id]).'" class="btn text-primary p-0" title="History"><i class="ti ti-history fs-6"></i></a>
                        <form action="'.route('admin.inventory.low-stock-alert.update', $product).'" method="POST" class="d-flex gap-1">
                            '.csrf_field().method_field('PATCH').'
                            <input type="number" min="0" step="0.0001" name="low_stock_alert" value="'.$threshold.'" class="form-control form-control-sm" style="width: 95px" placeholder="Threshold">
                            <button class="btn btn-sm btn-outline-primary" title="Save Threshold"><i class="ti ti-device-floppy"></i></button>
                        </form>
                    </div>';
                })
                ->rawColumns(['action'])
                ->toJson();
        }

        return view('admin.inventory.index');
    }

    public function history(Request $request): JsonResponse|View
    {
        if ($request->ajax()) {
            $movements = InventoryMovement::query()
                ->with([
                    'product.translations' => fn ($query) => $query->where('locale', 'en'),
                    'product.configurable.translations' => fn ($query) => $query->where('locale', 'en'),
                    'product.attributeValues.option.translations' => fn ($query) => $query->where('locale', 'en'),
                    'creator',
                    // new
                    'reference',
                ]);

            if ($request->filled('product_id')) {
                $movements->where('product_id', $request->integer('product_id'));
            }
            if ($request->filled('type')) {
                $movements->where('type', (string) $request->string('type'));
            }

            return DataTables::eloquent($movements)
                ->addColumn('product_name', fn (InventoryMovement $movement) => $this->productName($movement->product))
                ->filterColumn('product_name', function ($query, $keyword) {
                    $query->whereHas('product', function ($query) use ($keyword) {
                        $query->whereHas('translations', function ($query) use ($keyword) {
                            $query->where('locale', 'en')->where('name', 'like', "%{$keyword}%");
                        })->orWhereHas('configurable.translations', function ($query) use ($keyword) {
                            $query->where('locale', 'en')->where('name', 'like', "%{$keyword}%");
                        });
                    });
                })
                ->addColumn('sku', fn (InventoryMovement $movement) => $movement->product?->sku ?? '-')
                ->filterColumn('sku', function ($query, $keyword) {
                    $query->whereHas('product', fn ($query) => $query->where('sku', 'like', "%{$keyword}%"));
                })
                ->editColumn('type', fn (InventoryMovement $movement) => ucwords(str_replace('_', ' ', $movement->type)))
                // new
                ->addColumn('reference', function (InventoryMovement $movement) {
                    if (! $movement->reference) {
                        return '-';
                    }

                    return match (true) {
                        $movement->reference instanceof Order => sprintf(
                            '<a href="%s" class="text-decoration-none fw-semibold">%s</a>',
                            route('admin.orders.show', $movement->reference),
                            e($movement->reference->order_number)
                        ),

                        default => sprintf(
                            '%s #%s',
                            class_basename($movement->reference_type),
                            $movement->reference_id
                        ),
                    };
                })
                ->addColumn('created_by_name', fn (InventoryMovement $movement) => $movement->creator?->name ?? 'Deleted User')
                ->filterColumn('created_by_name', function ($query, $keyword) {
                    $query->whereHas('creator', fn ($query) => $query->where('name', 'like', "%{$keyword}%"));
                })
                ->editColumn('created_at', fn (InventoryMovement $movement) => $movement->created_at?->format('Y-m-d H:i:s'))
                // new
                ->rawColumns(['reference'])
                ->toJson();
        }

        $products = $this->inventoryProducts();

        return view('admin.inventory.history', compact('products'));
    }

    public function receive(Request $request): View
    {
        return view('admin.inventory.receive', [
            'products' => $this->inventoryProducts(),
            'selectedProductId' => $request->integer('product') ?: null,
        ]);
    }

    public function storeReceive(StoreInventoryReceiptRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $product = Product::findOrFail($data['product_id']);
        $this->inventoryService->receiveStock($product, $data, $request->user()->id);

        return redirect()->route('admin.inventory.index')->with('success', 'Stock received successfully.');
    }

    public function adjustment(Request $request): View
    {
        return view('admin.inventory.adjustment', [
            'products' => $this->inventoryProducts(),
            'selectedProductId' => $request->integer('product') ?: null,
        ]);
    }

    public function storeAdjustment(StoreInventoryAdjustmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $product = Product::findOrFail($data['product_id']);
        $this->inventoryService->adjustStock($product, $data, $request->user()->id);

        return redirect()->route('admin.inventory.index')->with('success', 'Inventory adjusted successfully.');
    }

    public function opening(Request $request): View
    {
        $products = $this->inventoryProductsQuery()
            ->whereDoesntHave('inventoryMovements')
            ->get();

        return view('admin.inventory.opening', [
            'products' => $products,
            'selectedProductId' => $request->integer('product') ?: null,
        ]);
    }

    public function storeOpening(StoreOpeningStockRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $product = Product::findOrFail($data['product_id']);
        $this->inventoryService->setOpeningStock($product, $data, $request->user()->id);

        return redirect()->route('admin.inventory.index')->with('success', 'Opening stock recorded successfully.');
    }

    public function stockCount(Request $request): View
    {
        return view('admin.inventory.stock-count', [
            'products' => $this->inventoryProducts(),
            'selectedProductId' => $request->integer('product') ?: null,
        ]);
    }

    public function storeStockCount(StoreStockCountRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $product = Product::findOrFail($data['product_id']);
        $this->inventoryService->recordStockCount($product, $data, $request->user()->id);

        return redirect()->route('admin.inventory.index')->with('success', 'Stock count recorded successfully.');
    }

    public function updateLowStockAlert(UpdateLowStockAlertRequest $request, Product $product): RedirectResponse
    {
        $this->inventoryService->updateLowStockAlert(
            $product,
            $request->validated()['low_stock_alert'] ?? null
        );

        return redirect()->route('admin.inventory.index')->with('success', 'Low-stock threshold updated successfully.');
    }

    private function inventoryProducts(): Collection
    {
        return $this->inventoryProductsQuery()->get();
    }

    private function inventoryProductsQuery(): Builder
    {
        return Product::query()
            ->where('type', 'simple')
            ->with([
                'inventory',
                'translations' => fn ($query) => $query->where('locale', 'en'),
                'configurable.translations' => fn ($query) => $query->where('locale', 'en'),
                'attributeValues.option.translations' => fn ($query) => $query->where('locale', 'en'),
            ])
            ->orderBy('sku');
    }

    private function formatNumber(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
    }

    private function productName(?Product $product): string
    {
        if (! $product) {
            return '-';
        }

        $name = $product->translations->first()?->name
            ?? $product->configurable?->translations->first()?->name
            ?? 'Product';

        if (! $product->configurable_id) {
            return $name;
        }

        $options = $product->attributeValues
            ->sortBy('attribute_id')
            ->map(fn ($value) => $value->option?->translations->first()?->label)
            ->filter()
            ->implode(' / ');

        return $options ? $name.' — '.$options : $name;
    }
}
