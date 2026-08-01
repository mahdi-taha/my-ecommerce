<?php

namespace App\Providers;

use App\Enums\NotificationAudienceCode;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('shop.components.topbar', function (IlluminateView $view): void {
            $customer = auth('customer')->user();
            $logoPath = (string) setting('store.store_logo_path', '');
            $logoUrl = filled($logoPath) && Storage::disk('public')->exists($logoPath)
                ? Storage::disk('public')->url($logoPath)
                : null;
            $notificationCount = $customer?->databaseNotifications()
                ->where('audience_code', NotificationAudienceCode::Customer->value)
                ->whereNull('read_at')
                ->count() ?? 0;

            $view->with([
                'topbarStoreName' => setting('store.store_name', config('app.name')),
                'topbarLogoUrl' => $logoUrl,
                'topbarPhone' => setting('store.store_phone', ''),
                'topbarFacebookUrl' => setting('store.facebook_url', ''),
                'topbarWhatsAppUrl' => setting('store.whatsapp_url', ''),
                'topbarInstagramUrl' => setting('store.instagram_url', ''),
                'topbarCurrencyCode' => setting('currency.default_currency', 'USD'),
                'topbarCustomer' => $customer,
                'topbarNotificationCount' => $notificationCount,
            ]);
        });

        View::composer('customer.account._navigation', function (IlluminateView $view): void {
            $notificationCount = auth('customer')->user()?->databaseNotifications()
                ->where('audience_code', NotificationAudienceCode::Customer->value)
                ->whereNull('read_at')
                ->count() ?? 0;

            $view->with('notificationCount', $notificationCount);
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
