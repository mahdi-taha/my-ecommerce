<?php

namespace App\Http\Controllers\Shop;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tax;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function show(string $url_key): View
    {
        $locale = app()->getLocale();

        $productQuery = Product::query()
            ->active()
            ->visible()
            ->whereNull('configurable_id')
            ->whereIn('type', [
                ProductType::Simple->value,
                ProductType::Configurable->value,
            ])
            ->whereHas('translations', fn (Builder $query) => $query
                ->where('locale', $locale)
                ->where('url_key', $url_key))
            ->with([
                'translations' => fn ($query) => $query
                    ->where('locale', $locale),
                'images' => fn ($query) => $query
                    ->reorder()
                    ->orderByDesc('is_base')
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'inventory',
                'tax' => fn ($query) => $query->active(),
                'categories' => fn ($query) => $query
                    ->where('status', true)
                    ->orderBy('position')
                    ->orderBy('categories.id')
                    ->with([
                        'translations' => fn ($query) => $query
                            ->where('locale', $locale),
                        'parentHierarchy',
                    ]),
                'attributeValues' => fn ($query) => $query
                    ->whereHas('attribute', fn ($query) => $query
                        ->where('is_active', true)
                        ->where('is_visible_on_front', true))
                    ->with([
                        'attribute' => fn ($query) => $query
                            ->where('is_active', true)
                            ->where('is_visible_on_front', true)
                            ->with([
                                'translations' => fn ($query) => $query
                                    ->where('locale', $locale),
                            ]),
                        'option.translations' => fn ($query) => $query
                            ->where('locale', $locale),
                    ])
                    ->orderBy('id'),
                'superAttributes' => fn ($query) => $query
                    ->orderBy('id')
                    ->with([
                        'attribute' => fn ($query) => $query->with([
                            'translations' => fn ($query) => $query
                                ->where('locale', $locale),
                            'options' => fn ($query) => $query->with([
                                'translations' => fn ($query) => $query
                                    ->where('locale', $locale),
                            ]),
                        ]),
                    ]),
                'variants' => fn ($query) => $query
                    ->active()
                    ->where('type', ProductType::Simple->value)
                    ->with([
                        'attributeValues',
                        'images',
                        'inventory',
                        'tax' => fn ($query) => $query->active(),
                    ]),
            ]);

        $this->withWishlistState($productQuery);
        $product = $productQuery->firstOrFail();

        $product->load([
            'relatedProducts' => fn ($query) => $query
                ->active()
                ->visible()
                ->where('type', ProductType::Simple->value)
                ->whereNull('configurable_id')
                ->whereKeyNot($product->getKey())
                ->whereHas('translations', fn ($query) => $query
                    ->where('locale', $locale))
                ->with([
                    'translations' => fn ($query) => $query
                        ->where('locale', $locale),
                    'images',
                    'inventory',
                    'tax' => fn ($query) => $query->active(),
                    'categories' => fn ($query) => $query
                        ->where('status', true)
                        ->orderBy('position')
                        ->orderBy('categories.id')
                        ->with([
                            'translations' => fn ($query) => $query
                                ->where('locale', $locale),
                        ]),
                ])
                ->when(Auth::guard('customer')->id(), fn (Builder $query, int $customerId) => $query
                    ->withExists([
                        'wishlistItems as is_wishlisted' => fn (Builder $query) => $query
                            ->whereHas('wishlist', fn (Builder $query) => $query
                                ->where('user_id', $customerId)),
                    ]))
                ->limit(4),
        ]);

        $translation = $product->translations->firstOrFail();
        $category = $product->categories->first();
        $breadcrumbCategories = $this->breadcrumbCategories($category);
        $specifications = $this->specifications($product);
        $galleryImages = $product->images
            ->map(function ($image): ?array {
                $url = $this->productImageUrl($image->path);

                return $url === null
                    ? null
                    : [
                        'url' => $url,
                        'is_base' => $image->is_base,
                        'is_placeholder' => false,
                    ];
            })
            ->filter()
            ->values();
        if ($galleryImages->isEmpty()) {
            $galleryImages->push([
                'url' => null,
                'is_base' => true,
                'is_placeholder' => true,
            ]);
        }
        $currencyCode = setting('currency.default_currency', 'USD');
        $taxMode = setting('tax.tax_mode', 'b2c');
        $defaultTax = $this->defaultTax();
        $relatedProducts = $product->relatedProducts;
        $isWishlisted = (bool) ($product->is_wishlisted ?? false);
        $isConfigurable = $product->type === ProductType::Configurable->value;
        $hasPositiveEffectivePrice = $product->hasPositiveEffectivePrice();
        $availableQuantity = $isConfigurable
            ? '0.0000'
            : ($product->inventory?->availableQuantity() ?? '0.0000');
        $inStock = ! $isConfigurable
            && $hasPositiveEffectivePrice
            && (float) $availableQuantity > 0;
        $eligibleVariants = $isConfigurable
            ? $product->eligibleStorefrontVariants()
            : collect();
        $configurablePriceRange = $isConfigurable
            ? $product->configurablePriceRange($eligibleVariants, $taxMode, $defaultTax)
            : null;
        [$configurableAttributes, $variantPresentation] = $isConfigurable
            ? $this->configurablePresentation($product, $eligibleVariants, $taxMode, $defaultTax)
            : [collect(), []];

        return view('shop.pages.product-details', compact(
            'product',
            'translation',
            'category',
            'breadcrumbCategories',
            'specifications',
            'galleryImages',
            'currencyCode',
            'taxMode',
            'defaultTax',
            'relatedProducts',
            'availableQuantity',
            'inStock',
            'isConfigurable',
            'configurableAttributes',
            'variantPresentation',
            'configurablePriceRange',
            'isWishlisted',
            'hasPositiveEffectivePrice'
        ));
    }

    /**
     * @return array{0: Collection, 1: array<string, array<string, mixed>>}
     */
    private function configurablePresentation(
        Product $product,
        Collection $variants,
        string $taxMode,
        ?Tax $defaultTax
    ): array {
        $usedOptionIds = $variants
            ->flatMap(fn (Product $variant) => $variant->attributeValues
                ->pluck('attribute_option_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();
        $attributes = $product->superAttributes
            ->map(function ($superAttribute) use ($usedOptionIds) {
                $attribute = $superAttribute->attribute;
                $options = $attribute?->options
                    ->whereIn('id', $usedOptionIds)
                    ->filter(fn ($option) => $option->translations->isNotEmpty())
                    ->values() ?? collect();

                if (! $attribute || $attribute->translations->isEmpty() || $options->isEmpty()) {
                    return null;
                }

                return [
                    'id' => (int) $attribute->getKey(),
                    'label' => $attribute->translations->first()->admin_name,
                    'options' => $options->map(fn ($option) => [
                        'id' => (int) $option->getKey(),
                        'label' => $option->translations->first()->label,
                    ])->all(),
                ];
            })
            ->filter()
            ->values();

        $presentation = [];

        foreach ($variants as $variant) {
            $options = $variant->attributeValues
                ->whereNotNull('attribute_option_id')
                ->pluck('attribute_option_id', 'attribute_id')
                ->mapWithKeys(fn ($optionId, $attributeId) => [
                    (string) $attributeId => (int) $optionId,
                ])
                ->sortKeys();
            $key = $options
                ->map(fn ($optionId, $attributeId) => $attributeId.':'.$optionId)
                ->implode('|');
            $available = $variant->inventory?->availableQuantity() ?? '0.0000';
            $taxRate = $variant->effectiveTaxRate($defaultTax);
            $formattedTaxRate = rtrim(
                rtrim(number_format($taxRate, 4, '.', ''), '0'),
                '.'
            );
            $primaryImageUrl = $variant->images
                ->sortBy([
                    ['is_base', 'desc'],
                    ['sort_order', 'asc'],
                    ['id', 'asc'],
                ])
                ->map(fn ($image): ?string => $this->productImageUrl($image->path))
                ->first(fn (?string $url): bool => $url !== null);

            $presentation[$key] = [
                'options' => $options->all(),
                'sku' => $variant->sku,
                'price' => format_store_price(
                    $variant->displayPrice($taxMode, $defaultTax),
                    setting('currency.default_currency', 'USD')
                ),
                'regular_price' => $variant->hasActiveSpecialPrice()
                    ? format_store_price(
                        $variant->displayRegularPrice($taxMode, $defaultTax),
                        setting('currency.default_currency', 'USD')
                    )
                    : null,
                'tax_label' => $taxRate > 0
                    ? ($taxMode === 'b2c'
                        ? __('shop.product_details.including_tax', ['rate' => $formattedTaxRate])
                        : __('shop.product_details.tax_at_checkout', ['rate' => $formattedTaxRate]))
                    : null,
                'available_quantity' => $available,
                'available_label' => (float) $available > 0
                    ? __('shop.product.available_quantity', [
                        'quantity' => rtrim(rtrim($available, '0'), '.'),
                    ])
                    : __('shop.product.out_of_stock'),
                'in_stock' => (float) $available > 0,
                'image_url' => $primaryImageUrl,
            ];
        }

        return [$attributes, $presentation];
    }

    private function breadcrumbCategories(?Category $category): Collection
    {
        $categories = collect();

        while ($category !== null) {
            if ($category->translations->isNotEmpty()) {
                $categories->prepend($category);
            }

            $category = $category->parentHierarchy;
        }

        return $categories;
    }

    private function specifications(Product $product): Collection
    {
        return $product->attributeValues
            ->groupBy('attribute_id')
            ->map(function (Collection $values) {
                $attribute = $values->first()?->attribute;
                $label = $attribute?->translations->first()?->admin_name;

                if (! $attribute || ! $label) {
                    return null;
                }

                $displayValues = $values
                    ->map(function ($value) {
                        if ($value->attribute_option_id !== null) {
                            return $value->option?->translations->first()?->label;
                        }

                        return trim((string) $value->value);
                    })
                    ->filter(fn ($value) => $value !== null && $value !== '')
                    ->unique()
                    ->values();

                if ($displayValues->isEmpty()) {
                    return null;
                }

                return [
                    'label' => $label,
                    'value' => $displayValues->implode(', '),
                    'sort_order' => $attribute->sort_order,
                    'attribute_id' => $attribute->getKey(),
                ];
            })
            ->filter()
            ->sortBy([
                ['sort_order', 'asc'],
                ['attribute_id', 'asc'],
            ])
            ->values();
    }

    private function defaultTax(): ?Tax
    {
        $defaultTaxId = setting('tax.default_tax_id');

        if (! $defaultTaxId) {
            return null;
        }

        return Tax::query()
            ->active()
            ->find($defaultTaxId);
    }

    private function productImageUrl(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    private function withWishlistState(Builder $query): void
    {
        if ($customerId = Auth::guard('customer')->id()) {
            $query->withExists([
                'wishlistItems as is_wishlisted' => fn (Builder $query) => $query
                    ->whereHas('wishlist', fn (Builder $query) => $query
                        ->where('user_id', $customerId)),
            ]);
        }
    }
}
