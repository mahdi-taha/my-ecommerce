<?php

namespace App\Services;

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

class StorefrontProductListingService
{
    private const PAGE_SIZE = 12;

    public function paginate(array $filters, string $locale): LengthAwarePaginator
    {
        $at = now();
        $query = Product::query()
            ->select('products.*')
            ->join('product_translations as listing_translation', function ($join) use ($locale): void {
                $join->on('listing_translation.product_id', '=', 'products.id')
                    ->where('listing_translation.locale', $locale);
            })
            ->whereNull('products.configurable_id')
            ->active()
            ->visible();

        $this->applyRootEligibility($query, $at);
        $query->withStorefrontCardData($locale, Auth::guard('customer')->id());
        $this->applyFilters($query, $filters, $at);
        $this->applyPriceProjection($query, $at);
        $this->applySorting($query, $filters['sort'] ?? 'newest');

        return $query->paginate(self::PAGE_SIZE)->withQueryString();
    }

    /** @return Collection<int, Category> */
    public function categoryTree(): Collection
    {
        return app('storefront.category_hierarchy')['tree'];
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

        if (isset($filters['category'])) {
            $categoryIds = $this->activeCategoryBranchIds((int) $filters['category']);
            $query->whereHas('categories', fn (Builder $query) => $query->whereKey($categoryIds));
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
            ->where('storefront_variants.type', ProductType::Simple->value)
            ->where('storefront_variants.status', true);
        $this->applyPositiveEffectivePrice($query, 'storefront_variants', $at);

        return $query
            ->whereRaw('(select count(*) from product_super_attributes as listing_required where listing_required.product_id = products.id) > 0')
            ->whereRaw('(select count(*) from product_attribute_values as listing_selected where listing_selected.product_id = storefront_variants.id and listing_selected.attribute_option_id is not null) = (select count(*) from product_super_attributes as listing_required where listing_required.product_id = products.id)')
            ->whereNotExists(function (QueryBuilder $query): void {
                $query->selectRaw('1')
                    ->from('product_attribute_values as listing_extra')
                    ->whereColumn('listing_extra.product_id', 'storefront_variants.id')
                    ->whereNotNull('listing_extra.attribute_option_id')
                    ->whereNotExists(function (QueryBuilder $query): void {
                        $query->selectRaw('1')
                            ->from('product_super_attributes as listing_allowed')
                            ->whereColumn('listing_allowed.product_id', 'products.id')
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
