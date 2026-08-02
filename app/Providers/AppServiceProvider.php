<?php

namespace App\Providers;

use App\Enums\NotificationAudienceCode;
use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as IlluminateView;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped('storefront.category_hierarchy', function (): array {
            $categories = Category::query()
                ->where('status', true)
                ->with(['translations' => fn ($query) => $query
                    ->where('locale', app()->getLocale())])
                ->orderBy('position')
                ->orderBy('id')
                ->get();

            $localizedCategories = $categories
                ->filter(fn (Category $category): bool => $category->translations->isNotEmpty());

            $categoriesByParent = $localizedCategories->groupBy(
                fn (Category $category): int => (int) ($category->parent_id ?? 0)
            );
            $buildTree = function (Collection $nodes) use (&$buildTree, $categoriesByParent): Collection {
                return $nodes->each(function (Category $category) use (&$buildTree, $categoriesByParent): void {
                    $category->setRelation(
                        'children',
                        $buildTree($categoriesByParent->get($category->id, collect()))
                    );
                });
            };
            $tree = $buildTree($categoriesByParent->get(0, collect()));
            $toNavigationArray = function (Collection $nodes) use (&$toNavigationArray): array {
                return $nodes->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->translations->first()->name,
                    'children' => $toNavigationArray($category->children),
                ])->values()->all();
            };

            return [
                'categories' => $categories->values(),
                'tree' => $tree,
                'navigation' => $toNavigationArray($tree),
            ];
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $customerNotificationCount = function (): int {
            $request = request();
            $attribute = 'storefront.customer_notification_count';

            if ($request->attributes->has($attribute)) {
                return (int) $request->attributes->get($attribute);
            }

            $count = auth('customer')->user()?->databaseNotifications()
                ->where('audience_code', NotificationAudienceCode::Customer->value)
                ->whereNull('read_at')
                ->count() ?? 0;

            $request->attributes->set($attribute, $count);

            return $count;
        };

        View::composer('shop.components.navbar', function (IlluminateView $view): void {
            $hierarchy = app('storefront.category_hierarchy');

            $view->with([
                'navbarStoreName' => setting('store.store_name', config('app.name')),
                'storefrontCategoryTree' => $hierarchy['tree'],
                'storefrontCategoryNavigation' => $hierarchy['navigation'],
            ]);
        });

        View::composer('shop.components.topbar', function (IlluminateView $view) use ($customerNotificationCount): void {
            $customer = auth('customer')->user();
            $logoPath = (string) setting('store.store_logo_path', '');
            $logoUrl = filled($logoPath) && Storage::disk('public')->exists($logoPath)
                ? Storage::disk('public')->url($logoPath)
                : null;
            $view->with([
                'topbarStoreName' => setting('store.store_name', config('app.name')),
                'topbarLogoUrl' => $logoUrl,
                'topbarPhone' => setting('store.store_phone', ''),
                'topbarFacebookUrl' => setting('store.facebook_url', ''),
                'topbarWhatsAppUrl' => setting('store.whatsapp_url', ''),
                'topbarInstagramUrl' => setting('store.instagram_url', ''),
                'topbarCurrencyCode' => setting('currency.default_currency', 'USD'),
                'topbarCustomer' => $customer,
                'topbarNotificationCount' => $customerNotificationCount(),
            ]);
        });

        View::composer('customer.account._navigation', function (IlluminateView $view) use ($customerNotificationCount): void {
            $view->with('notificationCount', $customerNotificationCount());
        });

        View::composer('components.admin-topbar', function (IlluminateView $view): void {
            $adminNotificationCount = auth('admin')->user()?->databaseNotifications()
                ->where('audience_code', NotificationAudienceCode::Administrator->value)
                ->whereNull('read_at')
                ->count() ?? 0;

            $view->with('adminNotificationCount', $adminNotificationCount);
        });
    }
}
