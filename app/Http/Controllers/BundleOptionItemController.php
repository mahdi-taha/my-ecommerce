<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveBundleOptionItemRequest;
use App\Models\BundleOption;
use App\Models\BundleOptionItem;
use App\Models\Product;
use App\Services\ProductService;

class BundleOptionItemController extends Controller
{
    public function __construct(private ProductService $productService) {}

    public function store(
        SaveBundleOptionItemRequest $request,
        Product $product,
        BundleOption $bundleOption
    ) {
        $this->ensureOptionBelongsToProduct($product, $bundleOption);

        $this->productService->createBundleOptionItem(
            $bundleOption,
            $request->validated()
        );

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('success', 'Bundle item created successfully.');
    }

    public function update(
        SaveBundleOptionItemRequest $request,
        Product $product,
        BundleOption $bundleOption,
        BundleOptionItem $bundleOptionItem
    ) {
        $this->ensureItemBelongsToOption(
            $product,
            $bundleOption,
            $bundleOptionItem
        );

        $this->productService->updateBundleOptionItem(
            $bundleOptionItem,
            $request->validated()
        );

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('success', 'Bundle item updated successfully.');
    }

    public function destroy(
        Product $product,
        BundleOption $bundleOption,
        BundleOptionItem $bundleOptionItem
    ) {
        $this->ensureItemBelongsToOption(
            $product,
            $bundleOption,
            $bundleOptionItem
        );
        $this->productService->deleteBundleOptionItem($bundleOptionItem);

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('success', 'Bundle item deleted successfully.');
    }

    private function ensureOptionBelongsToProduct(
        Product $product,
        BundleOption $bundleOption
    ): void {
        abort_unless(
            $product->type === 'bundle' &&
            $product->configurable_id === null &&
            $bundleOption->product_id === $product->id,
            404
        );
    }

    private function ensureItemBelongsToOption(
        Product $product,
        BundleOption $bundleOption,
        BundleOptionItem $bundleOptionItem
    ): void {
        $this->ensureOptionBelongsToProduct($product, $bundleOption);

        abort_unless(
            $bundleOptionItem->bundle_option_id === $bundleOption->id,
            404
        );
    }
}
