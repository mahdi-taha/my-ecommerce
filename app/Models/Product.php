<?php

namespace App\Models;

use App\Enums\ProductType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use LogicException;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'configurable_id',
        'type',
        'product_number',
        'sku',
        'price',
        'special_price',
        'special_price_from',
        'special_price_to',
        'use_default_tax',
        'tax_id',
        'is_new',
        'is_featured',
        'is_visible_individually',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:4',
        'special_price' => 'decimal:4',
        'special_price_from' => 'datetime',
        'special_price_to' => 'datetime',
        'use_default_tax' => 'boolean',
        'is_new' => 'boolean',
        'is_featured' => 'boolean',
        'is_visible_individually' => 'boolean',
        'status' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible_individually', true);
    }

    public function scopeOnSale(Builder $query, CarbonInterface $at): Builder
    {
        return $query
            ->whereNotNull('special_price')
            ->whereColumn('special_price', '<', 'price')
            ->where(function (Builder $query) use ($at) {
                $query->whereNull('special_price_from')
                    ->orWhere('special_price_from', '<=', $at);
            })
            ->where(function (Builder $query) use ($at) {
                $query->whereNull('special_price_to')
                    ->orWhere('special_price_to', '>=', $at);
            });
    }

    public function scopeZeroEffectivePrice(Builder $query, CarbonInterface $at): Builder
    {
        return $query->where(function (Builder $query) use ($at) {
            $query->where('price', '<=', 0)
                ->orWhere(function (Builder $query) use ($at) {
                    $query->onSale($at)->where('special_price', '<=', 0);
                });
        });
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query
            ->where('type', ProductType::Simple->value)
            ->where(function (Builder $query) {
                $query->whereDoesntHave('inventory')
                    ->orWhereHas('inventory', fn (Builder $query) => $query->outOfStock());
            });
    }

    public function scopePositiveEffectivePrice(
        Builder $query,
        CarbonInterface $at,
        string $table = 'products'
    ): Builder {
        return $query
            ->where("{$table}.price", '>', 0)
            ->where(function (Builder $query) use ($at, $table): void {
                $query->whereNull("{$table}.special_price")
                    ->orWhereColumn("{$table}.special_price", '>=', "{$table}.price")
                    ->orWhere("{$table}.special_price", '>', 0)
                    ->orWhere("{$table}.special_price_from", '>', $at)
                    ->orWhere("{$table}.special_price_to", '<', $at);
            });
    }

    public function scopeWithStorefrontCardData(
        Builder $query,
        string $locale,
        ?int $customerId = null
    ): Builder {
        $query->withCount('approvedReviews')->withAvg('approvedReviews', 'rating')->with([
            'translations' => fn ($query) => $query->where('locale', $locale),
            'images',
            'inventory',
            'superAttributes',
            'variants' => fn ($query) => $query
                ->active()
                ->where('type', ProductType::Simple->value)
                ->with([
                    'attributeValues',
                    'inventory',
                    'tax' => fn ($query) => $query->active(),
                ]),
            'tax' => fn ($query) => $query->active(),
            'categories' => fn ($query) => $query
                ->where('status', true)
                ->with([
                    'translations' => fn ($query) => $query->where('locale', $locale),
                ]),
        ]);

        if ($customerId !== null) {
            $query->withExists([
                'wishlistItems as is_wishlisted' => fn (Builder $query) => $query
                    ->whereHas('wishlist', fn (Builder $query) => $query
                        ->where('user_id', $customerId)),
            ]);
        }

        return $query;
    }

    public function hasActiveSpecialPrice(): bool
    {
        if ($this->special_price === null || (float) $this->special_price >= (float) $this->price) {
            return false;
        }

        $now = now();

        return ($this->special_price_from === null || $this->special_price_from->lte($now))
            && ($this->special_price_to === null || $this->special_price_to->gte($now));
    }

    public function effectivePrice(): string
    {
        return $this->hasActiveSpecialPrice()
            ? $this->special_price
            : $this->price;
    }

    public function hasPositiveEffectivePrice(): bool
    {
        return (float) $this->effectivePrice() > 0;
    }

    /**
     * @return Collection<int, Product>
     */
    public function eligibleStorefrontVariants(): Collection
    {
        if (! $this->relationLoaded('variants') || ! $this->relationLoaded('superAttributes')) {
            throw new LogicException('Configurable storefront variants must be eager loaded.');
        }

        if ($this->type !== ProductType::Configurable->value || $this->configurable_id !== null) {
            return collect();
        }

        $requiredAttributeIds = $this->superAttributes
            ->pluck('attribute_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values();

        return $this->variants
            ->filter(function (Product $variant) use ($requiredAttributeIds): bool {
                if (! $variant->relationLoaded('attributeValues')) {
                    throw new LogicException('Configurable variant attribute values must be eager loaded.');
                }

                if (! $variant->status
                    || $variant->type !== ProductType::Simple->value
                    || (int) $variant->configurable_id !== (int) $this->getKey()
                    || ! $variant->hasPositiveEffectivePrice()) {
                    return false;
                }

                $selectedAttributeIds = $variant->attributeValues
                    ->whereNotNull('attribute_option_id')
                    ->pluck('attribute_id')
                    ->map(fn ($id) => (int) $id)
                    ->sort()
                    ->values();

                return $requiredAttributeIds->isNotEmpty()
                    && $selectedAttributeIds->count() === $requiredAttributeIds->count()
                    && $selectedAttributeIds->all() === $requiredAttributeIds->all();
            })
            ->values();
    }

    /**
     * @param  Collection<int, Product>  $variants
     * @return array{minimum: float, maximum: float, regular_minimum: float, regular_maximum: float, show_regular_range: bool, common_tax_rate: ?float}|null
     */
    public function configurablePriceRange(
        Collection $variants,
        string $taxMode,
        ?Tax $defaultTax = null
    ): ?array {
        if ($variants->isEmpty()) {
            return null;
        }

        $displayPrices = $variants->map(
            fn (Product $variant): float => $variant->displayPrice($taxMode, $defaultTax)
        );
        $regularPrices = $variants->map(
            fn (Product $variant): float => $variant->displayRegularPrice($taxMode, $defaultTax)
        );
        $minimum = (float) $displayPrices->min();
        $maximum = (float) $displayPrices->max();
        $regularMinimum = (float) $regularPrices->min();
        $regularMaximum = (float) $regularPrices->max();
        $taxRates = $variants
            ->map(fn (Product $variant): string => number_format(
                $variant->effectiveTaxRate($defaultTax),
                4,
                '.',
                ''
            ))
            ->unique()
            ->values();

        return [
            'minimum' => $minimum,
            'maximum' => $maximum,
            'regular_minimum' => $regularMinimum,
            'regular_maximum' => $regularMaximum,
            'show_regular_range' => $variants->contains(
                fn (Product $variant): bool => $variant->hasActiveSpecialPrice()
            ) && (number_format($minimum, 4, '.', '') !== number_format($regularMinimum, 4, '.', '')
                || number_format($maximum, 4, '.', '') !== number_format($regularMaximum, 4, '.', '')),
            'common_tax_rate' => $taxRates->count() === 1
                ? (float) $taxRates->first()
                : null,
        ];
    }

    public function displayPrice(string $taxMode, ?Tax $defaultTax = null): float
    {
        return $this->applyTaxForDisplay($this->effectivePrice(), $taxMode, $defaultTax);
    }

    public function displayRegularPrice(string $taxMode, ?Tax $defaultTax = null): float
    {
        return $this->applyTaxForDisplay($this->price, $taxMode, $defaultTax);
    }

    public function effectiveTaxRate(?Tax $defaultTax = null): float
    {
        $tax = $this->use_default_tax ? $defaultTax : $this->tax;

        return $tax?->status ? max(0, (float) $tax->rate) : 0;
    }

    public function discountPercentage(): ?int
    {
        if (! $this->hasActiveSpecialPrice() || (float) $this->price <= 0) {
            return null;
        }

        return (int) round(
            (((float) $this->price - (float) $this->special_price) / (float) $this->price) * 100
        );
    }

    public function isWishlistEligible(): bool
    {
        return $this->status
            && $this->is_visible_individually
            && $this->configurable_id === null
            && in_array($this->type, [
                ProductType::Simple->value,
                ProductType::Configurable->value,
            ], true);
    }

    public function isWishlistAvailable(): bool
    {
        if (! $this->isWishlistEligible()) {
            return false;
        }

        if ($this->type === ProductType::Simple->value) {
            return (float) ($this->inventory?->availableQuantity() ?? 0) > 0;
        }

        return $this->variants->contains(fn (Product $variant) => $variant->status
            && $variant->type === ProductType::Simple->value
            && (float) ($variant->inventory?->availableQuantity() ?? 0) > 0);
    }

    public function mainImageUrl(): ?string
    {
        $image = $this->images->firstWhere('is_base', true) ?? $this->images->first();

        return $image
            ? Storage::disk('public')->url($image->path)
            : null;
    }

    public function configurable(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'configurable_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Product::class, 'configurable_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ProductTranslation::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'product_categories'
        )->withTimestamps();
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    public function relatedProducts(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_related_products',
            'product_id',
            'related_product_id'
        )
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function relatedToProducts(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_related_products',
            'related_product_id',
            'product_id'
        )
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->orderBy('sort_order');
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(ProductInventory::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function superAttributes(): HasMany
    {
        return $this->hasMany(ProductSuperAttribute::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->approved();
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    private function applyTaxForDisplay(string|float $price, string $taxMode, ?Tax $defaultTax): float
    {
        $amount = (float) $price;

        if ($taxMode !== 'b2c') {
            return $amount;
        }

        return $amount + ($amount * $this->effectiveTaxRate($defaultTax) / 100);
    }
}
