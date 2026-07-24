<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

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
        'business_mode',
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

    public function scopeActive(Builder $query): void
    {
        $query->where('status', true);
    }

    public function scopeVisible(Builder $query): void
    {
        $query->where('is_visible_individually', true);
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

    public function bundleOptions(): HasMany
    {
        return $this->hasMany(BundleOption::class)
            ->orderBy('sort_order');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
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
