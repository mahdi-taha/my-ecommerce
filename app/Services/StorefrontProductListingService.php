<?php

namespace App\Services;

use App\Enums\AttributeType;
use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StorefrontProductListingService
{
    private const PAGE_SIZE = 12;

    public function paginate(
        array $filters,
        string $locale,
        ?Category $category = null,
        ?CarbonInterface $at = null
    ): LengthAwarePaginator {
        $at ??= now();
        $query = $this->eligibleRootQuery($locale, $at)
            ->select('products.*');
        $categoryId = $category?->getKey() ?? ($filters['category'] ?? null);
        if ($categoryId !== null) {
            $this->applyCategoryConstraint($query, (int) $categoryId);
        }

        $query->withStorefrontCardData($locale, Auth::guard('customer')->id());
        $this->applyFilters($query, $filters, $at);
        $this->applyPriceProjection($query, $at);
        $this->applySorting($query, $filters['sort'] ?? 'newest');

        return $query->paginate(self::PAGE_SIZE)->withQueryString();
    }

    public function eligibleProductIdByUrlKey(
        string $urlKey,
        string $locale,
        ?CarbonInterface $at = null
    ): ?int {
        return $this->eligibleRootQuery($locale, $at ?? now())
            ->where('listing_translation.url_key', $urlKey)
            ->value('products.id');
    }

    public function productIsEligible(
        int $productId,
        string $locale,
        ?CarbonInterface $at = null
    ): bool {
        return $this->eligibleRootQuery($locale, $at ?? now())
            ->where('products.id', $productId)
            ->exists();
    }

    /** @return Collection<int, object{id: int, url_key: string}> */
    public function eligibleSitemapProducts(
        string $locale,
        ?CarbonInterface $at = null
    ): Collection {
        return $this->eligibleRootQuery($locale, $at ?? now())
            ->orderBy('products.id')
            ->get(['products.id', 'listing_translation.url_key']);
    }

    private function eligibleRootQuery(string $locale, CarbonInterface $at): Builder
    {
        $query = Product::query()
            ->join('product_translations as listing_translation', function ($join) use ($locale): void {
                $join->on('listing_translation.product_id', '=', 'products.id')
                    ->where('listing_translation.locale', $locale);
            })
            ->whereNull('products.configurable_id')
            ->active()
            ->visible();

        $this->applyRootEligibility($query, $at);

        return $query;
    }

    public function categoryBySlug(string $slug): ?Category
    {
        return app('storefront.category_hierarchy')['reachable_categories']
            ->first(fn (Category $category): bool => $category->translations->first()->slug === $slug);
    }

    public function categoryById(int $categoryId): ?Category
    {
        return app('storefront.category_hierarchy')['reachable_categories']->firstWhere('id', $categoryId);
    }

    /** @return array<int, array<string, mixed>> */
    public function categoryFacets(Category $category, string $locale, CarbonInterface $at): array
    {
        $attributes = DB::table('attributes')
            ->join('category_filterable_attributes as configured', 'configured.attribute_id', '=', 'attributes.id')
            ->join('attribute_translations as translation', function ($join) use ($locale): void {
                $join->on('translation.attribute_id', '=', 'attributes.id')
                    ->where('translation.locale', $locale);
            })
            ->where('configured.category_id', $category->getKey())
            ->where('attributes.is_active', true)
            ->where('attributes.is_filterable', true)
            ->whereIn('attributes.type', [AttributeType::Select->value, AttributeType::Multiselect->value])
            ->orderBy('attributes.sort_order')
            ->orderBy('attributes.id')
            ->get(['attributes.id', 'attributes.code', 'attributes.type', 'translation.admin_name']);

        if ($attributes->isEmpty()) {
            return [];
        }

        $attributeIds = $attributes->pluck('id');
        $usedOptionIds = $this->usedOptionIds($category, $attributeIds->all(), $locale, $at);
        $options = DB::table('attribute_options')
            ->join('attribute_translation_options as translation', function ($join) use ($locale): void {
                $join->on('translation.attribute_option_id', '=', 'attribute_options.id')
                    ->where('translation.locale', $locale);
            })
            ->whereIn('attribute_options.id', $usedOptionIds)
            ->whereIn('attribute_options.attribute_id', $attributeIds)
            ->orderBy('attribute_options.sort_order')
            ->orderBy('attribute_options.id')
            ->get(['attribute_options.id', 'attribute_options.attribute_id', 'attribute_options.code', 'translation.label'])
            ->groupBy('attribute_id');

        return $attributes->map(function ($attribute) use ($options): ?array {
            $attributeOptions = $options->get($attribute->id, collect());
            if ($attributeOptions->isEmpty()) {
                return null;
            }

            return [
                'id' => (int) $attribute->id,
                'code' => $attribute->code,
                'type' => $attribute->type,
                'label' => $attribute->admin_name,
                'options' => $attributeOptions->map(fn ($option): array => [
                    'id' => (int) $option->id,
                    'code' => $option->code,
                    'label' => $option->label,
                ])->values()->all(),
            ];
        })->filter()->values()->all();
    }

    /** @return array<int, array{attribute_id: int, option_ids: array<int>}> */
    public function validateAttributeFilters(array $selected, array $facets): array
    {
        $facetsByCode = collect($facets)->keyBy('code');
        $normalized = [];

        foreach ($selected as $attributeCode => $optionCodes) {
            $facet = is_string($attributeCode) ? $facetsByCode->get($attributeCode) : null;
            if (! $facet) {
                throw ValidationException::withMessages([
                    "attributes.{$attributeCode}" => __('validation.in', ['attribute' => $attributeCode]),
                ]);
            }

            $optionsByCode = collect($facet['options'])->keyBy('code');
            $optionIds = collect($optionCodes)->map(function (string $optionCode) use ($optionsByCode, $attributeCode): int {
                $option = $optionsByCode->get($optionCode);
                if (! $option) {
                    throw ValidationException::withMessages([
                        "attributes.{$attributeCode}" => __('validation.in', ['attribute' => $attributeCode]),
                    ]);
                }

                return $option['id'];
            })->values()->all();

            $normalized[] = ['attribute_id' => $facet['id'], 'option_ids' => $optionIds];
        }

        return $normalized;
    }

    /** @return Collection<int, Category> */
    public function categoryBreadcrumbs(Category $category): Collection
    {
        $categories = app('storefront.category_hierarchy')['reachable_categories']->keyBy('id');
        $breadcrumbs = collect([$category]);
        $parentId = $category->parent_id;

        while ($parentId !== null) {
            $parent = $categories->get($parentId);

            if (! $parent) {
                return collect();
            }

            $breadcrumbs->prepend($parent);
            $parentId = $parent->parent_id;
        }

        return $breadcrumbs->values();
    }

    private function applyRootEligibility(Builder $query, CarbonInterface $at): void
    {
        $query->where(function (Builder $query) use ($at): void {
            $query->where(function (Builder $query) use ($at): void {
                $query->where('products.type', ProductType::Simple->value)
                    ->positiveEffectivePrice($at, 'products');
            })->orWhere(function (Builder $query) use ($at): void {
                $query->where('products.type', ProductType::Configurable->value)
                    ->whereExists($this->eligibleVariantQuery($at));
            });
        });
    }

    private function applyFilters(Builder $query, array $filters, CarbonInterface $at): void
    {
        if (filled($filters['q'] ?? null)) {
            $term = '%'.$filters['q'].'%';
            $query->where(function (Builder $query) use ($term): void {
                $query->where('listing_translation.name', 'like', $term)
                    ->orWhere('listing_translation.short_description', 'like', $term);
            });
        }

        if (isset($filters['min_price']) || isset($filters['max_price'])) {
            $this->applyPriceRange($query, $filters, $at);
        }

        if (($filters['stock'] ?? null) === 'in') {
            $query->where(function (Builder $query) use ($at): void {
                $query->where(function (Builder $query): void {
                    $query->where('products.type', ProductType::Simple->value)
                        ->whereHas('inventory', fn (Builder $query) => $query->inStock());
                })->orWhere(function (Builder $query) use ($at): void {
                    $variants = $this->eligibleVariantQuery($at)
                        ->join('product_inventories as listing_inventory', 'listing_inventory.product_id', '=', 'storefront_variants.id')
                        ->where('listing_inventory.quantity', '>', 0);
                    $query->where('products.type', ProductType::Configurable->value)
                        ->whereExists($variants);
                });
            });
        }

        if ((bool) ($filters['sale'] ?? false)) {
            $query->where(function (Builder $query) use ($at): void {
                $query->where(function (Builder $query) use ($at): void {
                    $query->where('products.type', ProductType::Simple->value);
                    $this->applyActiveSale($query, 'products', $at);
                })->orWhere(function (Builder $query) use ($at): void {
                    $variants = $this->eligibleVariantQuery($at);
                    $this->applyActiveSale($variants, 'storefront_variants', $at);
                    $query->where('products.type', ProductType::Configurable->value)
                        ->whereExists($variants);
                });
            });
        }

        $query->when((bool) ($filters['featured'] ?? false), fn (Builder $query) => $query
            ->where('products.is_featured', true));
        $query->when((bool) ($filters['new'] ?? false), fn (Builder $query) => $query
            ->where('products.is_new', true));

        if (($filters['_attribute_filters'] ?? []) !== []) {
            $this->applyAttributeFilters($query, $filters['_attribute_filters'], $at);
        }
    }

    private function applyAttributeFilters(Builder $query, array $attributeFilters, CarbonInterface $at): void
    {
        $query->where(function (Builder $query) use ($attributeFilters, $at): void {
            $query->where(function (Builder $query) use ($attributeFilters): void {
                $query->where('products.type', ProductType::Simple->value);
                foreach ($attributeFilters as $filter) {
                    $query->whereExists(function (QueryBuilder $values) use ($filter): void {
                        $values->selectRaw('1')
                            ->from('product_attribute_values as listing_filter_values')
                            ->whereColumn('listing_filter_values.product_id', 'products.id')
                            ->where('listing_filter_values.attribute_id', $filter['attribute_id'])
                            ->whereIn('listing_filter_values.attribute_option_id', $filter['option_ids']);
                    });
                }
            })->orWhere(function (Builder $query) use ($attributeFilters, $at): void {
                $variants = $this->eligibleVariantQuery($at);
                foreach ($attributeFilters as $filter) {
                    $variants->whereExists(function (QueryBuilder $values) use ($filter): void {
                        $values->selectRaw('1')
                            ->from('product_attribute_values as listing_filter_values')
                            ->whereColumn('listing_filter_values.product_id', 'storefront_variants.id')
                            ->where('listing_filter_values.attribute_id', $filter['attribute_id'])
                            ->whereIn('listing_filter_values.attribute_option_id', $filter['option_ids']);
                    });
                }
                $query->where('products.type', ProductType::Configurable->value)
                    ->whereExists($variants);
            });
        });
    }

    private function applyPriceRange(Builder $query, array $filters, CarbonInterface $at): void
    {
        $query->where(function (Builder $query) use ($filters, $at): void {
            $query->where(function (Builder $query) use ($filters, $at): void {
                $query->where('products.type', ProductType::Simple->value);
                $this->applyEffectivePriceBounds($query, 'products', $filters, $at);
            })->orWhere(function (Builder $query) use ($filters, $at): void {
                $variants = $this->eligibleVariantQuery($at);
                $this->applyEffectivePriceBounds($variants, 'storefront_variants', $filters, $at);
                $query->where('products.type', ProductType::Configurable->value)
                    ->whereExists($variants);
            });
        });
    }

    private function applyPriceProjection(Builder $query, CarbonInterface $at): void
    {
        [$simpleExpression, $simpleBindings] = $this->effectivePriceExpression('products', $at);
        $variantMinimum = $this->eligibleVariantQuery($at);
        [$variantExpression, $variantBindings] = $this->effectivePriceExpression('storefront_variants', $at);
        $variantMinimum->selectRaw("MIN({$variantExpression})", $variantBindings);

        $query->selectRaw("{$simpleExpression} as storefront_simple_price", $simpleBindings)
            ->addSelect(['storefront_variant_price' => $variantMinimum]);
    }

    private function applySorting(Builder $query, string $sort): void
    {
        match ($sort) {
            'oldest' => $query->orderBy('products.created_at')->orderBy('products.id'),
            'price_asc' => $query->orderByRaw(
                'CASE WHEN products.type = ? THEN storefront_simple_price ELSE storefront_variant_price END ASC',
                [ProductType::Simple->value]
            )->orderBy('products.id'),
            'price_desc' => $query->orderByRaw(
                'CASE WHEN products.type = ? THEN storefront_simple_price ELSE storefront_variant_price END DESC',
                [ProductType::Simple->value]
            )->orderByDesc('products.id'),
            'name_asc' => $query->orderBy('listing_translation.name')->orderBy('products.id'),
            'name_desc' => $query->orderByDesc('listing_translation.name')->orderByDesc('products.id'),
            default => $query->orderByDesc('products.created_at')->orderByDesc('products.id'),
        };
    }

    private function eligibleVariantQuery(CarbonInterface $at): QueryBuilder
    {
        $query = DB::table('products as storefront_variants')
            ->whereColumn('storefront_variants.configurable_id', 'products.id')
            ->where('storefront_variants.type', ProductType::Simple->value);

        $this->applyEligibleVariantConstraints($query, 'storefront_variants', 'products', $at);

        return $query;
    }

    private function applyEligibleVariantConstraints(
        QueryBuilder $query,
        string $variantTable,
        string $parentTable,
        CarbonInterface $at
    ): void {
        $query->where("{$variantTable}.status", true);
        $this->applyPositiveEffectivePrice($query, $variantTable, $at);
        $query->whereRaw("(select count(*) from product_super_attributes as listing_required where listing_required.product_id = {$parentTable}.id) > 0")
            ->whereRaw("(select count(*) from product_attribute_values as listing_selected where listing_selected.product_id = {$variantTable}.id and listing_selected.attribute_option_id is not null) = (select count(*) from product_super_attributes as listing_required where listing_required.product_id = {$parentTable}.id)")
            ->whereNotExists(function (QueryBuilder $query) use ($variantTable, $parentTable): void {
                $query->selectRaw('1')
                    ->from('product_attribute_values as listing_extra')
                    ->whereColumn('listing_extra.product_id', "{$variantTable}.id")
                    ->whereNotNull('listing_extra.attribute_option_id')
                    ->whereNotExists(function (QueryBuilder $query) use ($parentTable): void {
                        $query->selectRaw('1')
                            ->from('product_super_attributes as listing_allowed')
                            ->whereColumn('listing_allowed.product_id', "{$parentTable}.id")
                            ->whereColumn('listing_allowed.attribute_id', 'listing_extra.attribute_id');
                    });
            });
    }

    private function applyPositiveEffectivePrice(Builder|QueryBuilder $query, string $table, CarbonInterface $at): void
    {
        [$expression, $bindings] = $this->effectivePriceExpression($table, $at);
        $query->whereRaw("{$expression} > 0", $bindings);
    }

    private function applyEffectivePriceBounds(Builder|QueryBuilder $query, string $table, array $filters, CarbonInterface $at): void
    {
        [$expression, $bindings] = $this->effectivePriceExpression($table, $at);

        if (isset($filters['min_price'])) {
            $query->whereRaw("{$expression} >= CAST(? AS DECIMAL(15, 4))", [...$bindings, $filters['min_price']]);
        }
        if (isset($filters['max_price'])) {
            $query->whereRaw("{$expression} <= CAST(? AS DECIMAL(15, 4))", [...$bindings, $filters['max_price']]);
        }
    }

    private function applyActiveSale(Builder|QueryBuilder $query, string $table, CarbonInterface $at): void
    {
        $query->whereNotNull("{$table}.special_price")
            ->whereColumn("{$table}.special_price", '<', "{$table}.price")
            ->where("{$table}.special_price", '>', 0)
            ->where(function ($query) use ($table, $at): void {
                $query->whereNull("{$table}.special_price_from")
                    ->orWhere("{$table}.special_price_from", '<=', $at);
            })
            ->where(function ($query) use ($table, $at): void {
                $query->whereNull("{$table}.special_price_to")
                    ->orWhere("{$table}.special_price_to", '>=', $at);
            });
    }

    /** @return array{0: string, 1: array<int, CarbonInterface>} */
    private function effectivePriceExpression(string $table, CarbonInterface $at): array
    {
        return [
            "CASE WHEN {$table}.special_price IS NOT NULL AND {$table}.special_price < {$table}.price AND ({$table}.special_price_from IS NULL OR {$table}.special_price_from <= ?) AND ({$table}.special_price_to IS NULL OR {$table}.special_price_to >= ?) THEN {$table}.special_price ELSE {$table}.price END",
            [$at, $at],
        ];
    }

    /** @param array<int> $attributeIds
     * @return array<int>
     */
    private function usedOptionIds(
        Category $category,
        array $attributeIds,
        string $locale,
        CarbonInterface $at
    ): array {
        $eligibleRoots = $this->eligibleRootQuery($locale, $at);
        $this->applyCategoryConstraint($eligibleRoots, $category->getKey());

        $simpleRoots = (clone $eligibleRoots)
            ->where('products.type', ProductType::Simple->value)
            ->select('products.id');
        $simpleOptions = DB::query()
            ->fromSub($simpleRoots, 'eligible_roots')
            ->join('product_attribute_values as facet_values', 'facet_values.product_id', '=', 'eligible_roots.id')
            ->whereIn('facet_values.attribute_id', $attributeIds)
            ->whereNotNull('facet_values.attribute_option_id')
            ->select('facet_values.attribute_option_id');

        $configurableRoots = (clone $eligibleRoots)
            ->where('products.type', ProductType::Configurable->value)
            ->select('products.id');
        $configurableOptions = DB::table('products as facet_variants')
            ->joinSub($configurableRoots, 'eligible_roots', function ($join): void {
                $join->on('facet_variants.configurable_id', '=', 'eligible_roots.id');
            })
            ->join('product_attribute_values as facet_values', 'facet_values.product_id', '=', 'facet_variants.id')
            ->where('facet_variants.type', ProductType::Simple->value)
            ->whereIn('facet_values.attribute_id', $attributeIds)
            ->whereNotNull('facet_values.attribute_option_id')
            ->select('facet_values.attribute_option_id');
        $this->applyEligibleVariantConstraints($configurableOptions, 'facet_variants', 'eligible_roots', $at);

        return $simpleOptions->union($configurableOptions)
            ->distinct()
            ->pluck('attribute_option_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function applyCategoryConstraint(Builder $query, int $categoryId): void
    {
        $categoryIds = $this->activeCategoryBranchIds($categoryId);
        $query->whereHas('categories', fn (Builder $query) => $query->whereKey($categoryIds));
    }

    /** @return array<int> */
    private function activeCategoryBranchIds(int $categoryId): array
    {
        $hierarchy = app('storefront.category_hierarchy');
        $byParent = $hierarchy['categories']->groupBy(
            fn ($category): int => (int) ($category->parent_id ?? 0)
        );
        $ids = [];
        $pending = [$categoryId];

        while ($pending !== []) {
            $id = array_shift($pending);
            $ids[] = $id;
            foreach ($byParent->get($id, collect()) as $child) {
                $pending[] = (int) $child->getKey();
            }
        }

        return $ids;
    }
}
