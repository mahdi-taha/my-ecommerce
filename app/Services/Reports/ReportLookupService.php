<?php

namespace App\Services\Reports;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportLookupService
{
    public function customers(string $search): Collection
    {
        return User::query()
            ->customers()
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'name', 'email', 'has_account'])
            ->map(fn (User $customer) => [
                'id' => $customer->getKey(),
                'text' => $this->personLabel($customer).($customer->has_account ? '' : ' (Manual)'),
            ]);
    }

    public function products(string $search): Collection
    {
        return Product::query()
            ->with([
                'translations' => fn ($query) => $query->where('locale', 'en'),
                'configurable.translations' => fn ($query) => $query->where('locale', 'en'),
            ])
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
                $query->where('sku', 'like', "%{$search}%")
                    ->orWhere('product_number', 'like', "%{$search}%")
                    ->orWhereHas('translations', fn (Builder $query) => $query
                        ->where('locale', 'en')->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('configurable.translations', fn (Builder $query) => $query
                        ->where('locale', 'en')->where('name', 'like', "%{$search}%"));
            }))
            ->orderBy('sku')
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->getKey(),
                'text' => $this->productLabel($product),
            ]);
    }

    public function categories(string $search): Collection
    {
        return Category::query()
            ->with([
                'translations' => fn ($query) => $query->where('locale', 'en'),
                'parent.translations' => fn ($query) => $query->where('locale', 'en'),
                'parent.parent.translations' => fn ($query) => $query->where('locale', 'en'),
            ])
            ->when($search !== '', fn (Builder $query) => $query->whereHas('translations', fn (Builder $query) => $query
                ->where('locale', 'en')->where('name', 'like', "%{$search}%")))
            ->orderBy('level')
            ->orderBy('position')
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->getKey(),
                'text' => $this->categoryLabel($category),
            ]);
    }

    public function administrators(string $search): Collection
    {
        return User::query()
            ->admins()
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'name', 'email', 'is_active'])
            ->map(fn (User $administrator) => [
                'id' => $administrator->getKey(),
                'text' => $this->personLabel($administrator).($administrator->is_active ? '' : ' (Inactive)'),
            ]);
    }

    private function personLabel(User $user): string
    {
        return $user->name.($user->email ? " — {$user->email}" : '');
    }

    private function productLabel(Product $product): string
    {
        $name = $product->translations->first()?->name
            ?? $product->configurable?->translations->first()?->name
            ?? 'Product';

        return "{$name} — {$product->sku}";
    }

    private function categoryLabel(Category $category): string
    {
        $labels = collect([$category->parent?->parent, $category->parent, $category])
            ->filter()
            ->map(fn (Category $node) => $node->translations->first()?->name)
            ->filter();

        return $labels->implode(' > ');
    }
}
