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
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function show(string $url_key): View
    {
        $locale = app()->getLocale();

        $product = Product::query()
            ->active()
            ->visible()
            ->where('type', ProductType::Simple->value)
            ->whereNull('configurable_id')
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
            ])
            ->firstOrFail();

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
                ->limit(4),
        ]);

        $translation = $product->translations->firstOrFail();
        $category = $product->categories->first();
        $breadcrumbCategories = $this->breadcrumbCategories($category);
        $specifications = $this->specifications($product);
        $galleryImages = $product->images
            ->map(fn ($image) => [
                'url' => Storage::disk('public')->url($image->path),
                'is_base' => $image->is_base,
            ]);
        $currencyCode = setting('currency.default_currency', 'USD');
        $taxMode = setting('tax.tax_mode', 'b2c');
        $defaultTax = $this->defaultTax();
        $relatedProducts = $product->relatedProducts;
        $availableQuantity = $product->inventory?->availableQuantity() ?? '0.0000';
        $inStock = (float) $availableQuantity > 0;

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
            'inStock'
        ));
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
}
