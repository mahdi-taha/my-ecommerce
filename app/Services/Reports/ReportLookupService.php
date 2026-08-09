<?php

namespace App\Services\Reports;

use App\DTOs\Reports\ReportFilters;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportLookupService
{
    public function selectedOptions(ReportFilters $filters, array $enabled): array
    {
        return array_filter([
            'customer_id' => $this->selectedCustomer($filters->customerId, $enabled),
            'product_id' => $this->selectedProduct($filters->productId, $enabled),
            'category_id' => $this->selectedCategory($filters->categoryId, $enabled),
            'administrator_id' => $this->selectedAdministrator($filters->administratorId, $enabled),
        ]);
    }

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

    private function selectedCustomer(?int $id, array $enabled): ?array
    {
        if ($id === null || ! in_array('customer_id', $enabled, true)) {
            return null;
        }

        $customer = User::query()->customers()->find($id, ['id', 'name', 'email', 'has_account']);

        return $customer ? [
            'id' => $customer->getKey(),
            'text' => $this->personLabel($customer).($customer->has_account ? '' : ' (Manual)'),
        ] : null;
    }

    private function selectedProduct(?int $id, array $enabled): ?array
    {
        if ($id === null || ! in_array('product_id', $enabled, true)) {
            return null;
        }

        $product = Product::query()->with([
            'translations' => fn ($query) => $query->where('locale', 'en'),
            'configurable.translations' => fn ($query) => $query->where('locale', 'en'),
        ])->find($id);

        return $product ? ['id' => $product->getKey(), 'text' => $this->productLabel($product)] : null;
    }

    private function selectedCategory(?int $id, array $enabled): ?array
    {
        if ($id === null || ! in_array('category_id', $enabled, true)) {
            return null;
        }

        $category = Category::query()->with([
            'translations' => fn ($query) => $query->where('locale', 'en'),
            'parent.translations' => fn ($query) => $query->where('locale', 'en'),
            'parent.parent.translations' => fn ($query) => $query->where('locale', 'en'),
        ])->find($id);

        return $category ? ['id' => $category->getKey(), 'text' => $this->categoryLabel($category)] : null;
    }

    private function selectedAdministrator(?int $id, array $enabled): ?array
    {
        if ($id === null || ! in_array('administrator_id', $enabled, true)) {
            return null;
        }

        $administrator = User::query()->admins()->find($id, ['id', 'name', 'email', 'is_active']);

        return $administrator ? [
            'id' => $administrator->getKey(),
            'text' => $this->personLabel($administrator).($administrator->is_active ? '' : ' (Inactive)'),
        ] : null;
    }
}
