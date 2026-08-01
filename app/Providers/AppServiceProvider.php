<?php

namespace App\Providers;

use App\Enums\NotificationAudienceCode;
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
