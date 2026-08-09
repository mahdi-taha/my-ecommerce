<?php

namespace App\Providers;

use App\Enums\NotificationAudienceCode;
use App\Models\Category;
use App\Services\StorefrontContentService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
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
        $this->app->bind('storefront.category_hierarchy', function (): array {
            $attribute = 'storefront.category_hierarchy.'.app()->getLocale();
            if (request()->attributes->has($attribute)) {
                return request()->attributes->get($attribute);
            }

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
            $reachableCategories = collect();
            $collectReachable = function (Collection $nodes) use (&$collectReachable, $reachableCategories): void {
                foreach ($nodes as $category) {
                    $reachableCategories->push($category);
                    $collectReachable($category->children);
                }
            };
            $collectReachable($tree);
            $toNavigationArray = function (Collection $nodes, int $depth = 0) use (&$toNavigationArray): array {
                if ($depth >= 3) {
                    return [];
                }

                return $nodes->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->translations->first()->name,
                    'url' => route('shop.categories.show', ['slug' => $category->translations->first()->slug]),
                    'children' => $toNavigationArray($category->children, $depth + 1),
                ])->values()->all();
            };

            $hierarchy = [
                'categories' => $categories->values(),
                'reachable_categories' => $reachableCategories,
                'tree' => $tree,
                'navigation' => $toNavigationArray($tree),
            ];
            request()->attributes->set($attribute, $hierarchy);

            return $hierarchy;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $defaultLocale = in_array(config('app.locale'), ['en', 'ar'], true) ? config('app.locale') : 'en';
        URL::defaults(['locale' => $defaultLocale]);
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

        $validSocialUrl = static function (mixed $value): ?string {
            $url = trim((string) $value);

            if ($url === '' || Validator::make(
                ['url' => $url],
                ['url' => ['url:http,https', 'max:2048']]
            )->fails()) {
                return null;
            }

            return $url;
        };

        $storefrontIdentity = function () use ($validSocialUrl): array {
            $request = request();
            $attribute = 'storefront.store_identity';

            if ($request->attributes->has($attribute)) {
                return $request->attributes->get($attribute);
            }

            $logoPath = trim((string) setting('store.store_logo_path', ''));
            $identity = [
                'name' => (string) setting('store.store_name', config('app.name')),
                'logo_url' => $logoPath !== '' && Storage::disk('public')->exists($logoPath)
                    ? Storage::disk('public')->url($logoPath)
                    : null,
                'currency_code' => (string) setting('currency.default_currency', 'USD'),
                'social_links' => [
                    'facebook' => $validSocialUrl(setting('store.facebook_url', '')),
                    'instagram' => $validSocialUrl(setting('store.instagram_url', '')),
                    'whatsapp' => $validSocialUrl(setting('store.whatsapp_url', '')),
                ],
            ];
            $request->attributes->set($attribute, $identity);

            return $identity;
        };

        View::composer('shop.layouts.app', function (IlluminateView $view) use ($storefrontIdentity): void {
            $view->with('storefrontFaviconUrl', $storefrontIdentity()['logo_url']);
        });

        View::composer('shop.components.navbar', function (IlluminateView $view) use ($storefrontIdentity): void {
            $hierarchy = app('storefront.category_hierarchy');
            $identity = $storefrontIdentity();

            $view->with([
                'navbarStoreName' => $identity['name'],
                'navbarLogoUrl' => $identity['logo_url'],
                'navbarCurrencyCode' => $identity['currency_code'],
                'storefrontCategoryTree' => $hierarchy['tree'],
                'storefrontCategoryNavigation' => $hierarchy['navigation'],
            ]);
        });

        View::composer('shop.components.topbar', function (IlluminateView $view) use ($customerNotificationCount, $storefrontIdentity): void {
            $customer = auth('customer')->user();
            $identity = $storefrontIdentity();
            $view->with([
                'topbarStoreName' => $identity['name'],
                'topbarLogoUrl' => $identity['logo_url'],
                'topbarPhone' => setting('store.store_phone', ''),
                'topbarFacebookUrl' => $identity['social_links']['facebook'],
                'topbarWhatsAppUrl' => $identity['social_links']['whatsapp'],
                'topbarInstagramUrl' => $identity['social_links']['instagram'],
                'topbarCurrencyCode' => $identity['currency_code'],
                'topbarCustomer' => $customer,
                'topbarNotificationCount' => $customerNotificationCount(),
            ]);
        });

        View::composer(['shop.components.footer', 'shop.components.navbar'], function (IlluminateView $view) use ($storefrontIdentity): void {
            $content = app(StorefrontContentService::class);
            $locale = app()->getLocale();
            $view->with([
                'storefrontFooterPages' => $content->footerPages($locale),
                'storefrontContactPage' => $content->pageByCode('contact', $locale),
                'storefrontSocialLinks' => $storefrontIdentity()['social_links'],
            ]);
        });

        View::composer('customer.account._navigation', function (IlluminateView $view) use ($customerNotificationCount): void {
            $view->with('notificationCount', $customerNotificationCount());
        });

        $adminIdentity = function (): array {
            $request = request();
            $attribute = 'admin.store_identity';

            if ($request->attributes->has($attribute)) {
                return $request->attributes->get($attribute);
            }

            $logoPath = trim((string) setting('store.store_logo_path', ''));
            $identity = [
                'name' => (string) setting('store.store_name', config('app.name')),
                'logo_url' => $logoPath !== '' && Storage::disk('public')->exists($logoPath)
                    ? Storage::disk('public')->url($logoPath)
                    : null,
            ];
            $request->attributes->set($attribute, $identity);

            return $identity;
        };

        View::composer('components.admin-main', function (IlluminateView $view) use ($adminIdentity): void {
            $view->with('adminFaviconUrl', $adminIdentity()['logo_url']);
        });

        View::composer('components.admin-topbar', function (IlluminateView $view) use ($adminIdentity): void {
            $adminNotificationCount = auth('admin')->user()?->databaseNotifications()
                ->where('audience_code', NotificationAudienceCode::Administrator->value)
                ->whereNull('read_at')
                ->count() ?? 0;

            $identity = $adminIdentity();

            // $view->with('adminNotificationCount', $adminNotificationCount);
            $view->with([
                'adminNotificationCount' => $adminNotificationCount,
                'adminCompanyName' => $identity['name'],
                'adminLogoUrl' => $identity['logo_url'],
            ]);
        });

        View::composer('components.admin-sidebar', function (IlluminateView $view) use ($adminIdentity): void {
            $view->with([
                'adminCompanyName' => $adminIdentity()['name'],
            ]);
        });
    }
}
