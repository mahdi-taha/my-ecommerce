<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveBundleOptionRequest;
use App\Models\BundleOption;
use App\Models\Product;
use App\Services\ProductService;

class BundleOptionController extends Controller
{
    public function __construct(private ProductService $productService) {}

    public function store(
        SaveBundleOptionRequest $request,
        Product $product
    ) {
        $this->ensureBundleParent($product);

        $this->productService->createBundleOption(
            $product,
            $request->validated()
        );

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('success', 'Bundle option created successfully.');
    }

    public function update(
        SaveBundleOptionRequest $request,
        Product $product,
        BundleOption $bundleOption
    ) {
        $this->ensureOptionBelongsToProduct($product, $bundleOption);

        $this->productService->updateBundleOption(
            $bundleOption,
            $request->validated()
        );

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('success', 'Bundle option updated successfully.');
    }

    public function destroy(
        Product $product,
        BundleOption $bundleOption
    ) {
        $this->ensureOptionBelongsToProduct($product, $bundleOption);
        $this->productService->deleteBundleOption($bundleOption);

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('success', 'Bundle option and its items deleted successfully.');
    }

    private function ensureBundleParent(Product $product): void
    {
        abort_unless(
            $product->type === 'bundle' &&
            $product->configurable_id === null,
            404
        );
    }

    private function ensureOptionBelongsToProduct(
        Product $product,
        BundleOption $bundleOption
    ): void {
        $this->ensureBundleParent($product);

        abort_unless($bundleOption->product_id === $product->id, 404);
    }
}
