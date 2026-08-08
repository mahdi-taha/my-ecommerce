<?php

use App\Http\Controllers\Admin\CmsPageController as AdminCmsPageController;
use App\Http\Controllers\Admin\HomepageBannerController as AdminHomepageBannerController;
use App\Http\Controllers\Admin\HomepageServiceController as AdminHomepageServiceController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\OrderCancellationRequestController as AdminOrderCancellationRequestController;
use App\Http\Controllers\Admin\OrderCreationController as AdminOrderCreationController;
use App\Http\Controllers\Admin\ProductReviewController as AdminProductReviewController;
use App\Http\Controllers\Admin\RefundController as AdminRefundController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\AttributeOptionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\CustomerAccountController;
use App\Http\Controllers\CustomerAddressController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerPasswordResetController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShippingMethodController;
use App\Http\Controllers\Shop\Account\NotificationController as ShopAccountNotificationController;
use App\Http\Controllers\Shop\Account\OrderCancellationRequestController as ShopOrderCancellationRequestController;
use App\Http\Controllers\Shop\Account\OrderController as ShopAccountOrderController;
use App\Http\Controllers\Shop\Account\ReviewController as ShopAccountReviewController;
use App\Http\Controllers\Shop\CartController as ShopCartController;
use App\Http\Controllers\Shop\CheckoutController as ShopCheckoutController;
use App\Http\Controllers\Shop\CheckoutCouponController as ShopCheckoutCouponController;
use App\Http\Controllers\Shop\CmsPageController as ShopCmsPageController;
use App\Http\Controllers\Shop\LegacyStorefrontRedirectController;
use App\Http\Controllers\Shop\LocaleController as ShopLocaleController;
use App\Http\Controllers\Shop\ProductController as ShopProductController;
use App\Http\Controllers\Shop\ProductListingController as ShopProductListingController;
use App\Http\Controllers\Shop\ProductReviewController as ShopProductReviewController;
use App\Http\Controllers\Shop\WishlistController as ShopWishlistController;
use App\Http\Controllers\VariantController;
use App\Http\Middleware\EnforceActiveCustomerSession;
use App\Http\Middleware\SetStorefrontLocale;
use App\Http\Middleware\ShareStorefrontWishlist;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/robots.txt', [SeoController::class, 'robots'])
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, ValidateCsrfToken::class, SetStorefrontLocale::class, EnforceActiveCustomerSession::class])
    ->name('seo.robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, ValidateCsrfToken::class, SetStorefrontLocale::class, EnforceActiveCustomerSession::class])
    ->name('seo.sitemap');

Route::prefix('{locale}')->whereIn('locale', ['en', 'ar'])->group(function () {
    Route::post('/locale/{targetLocale}', ShopLocaleController::class)
        ->whereIn('targetLocale', ['en', 'ar'])
        ->name('shop.locale.update');

    Route::middleware(['storefront.cart', ShareStorefrontWishlist::class])->group(function () {
        Route::get('/', [HomeController::class, 'index'])->name('shop.home');
        Route::get('/shop', [ShopProductListingController::class, 'index'])->name('shop.products.index');
        Route::get('/top-selling', [ShopProductListingController::class, 'topSelling'])
            ->name('shop.products.top-selling');
        Route::get('/categories/{slug}', [ShopProductListingController::class, 'category'])
            ->name('shop.categories.show');
        Route::get('/pages/{slug}', [ShopCmsPageController::class, 'show'])->name('shop.pages.show');
        Route::get('/products/{url_key}', [ShopProductController::class, 'show'])
            ->name('shop.products.show');
        Route::post('/products/{product}/reviews', [ShopProductReviewController::class, 'store'])
            ->middleware(['auth:customer', 'customer'])->name('shop.products.reviews.store');
        Route::get('/cart', [ShopCartController::class, 'index'])->name('shop.cart.index');
        Route::post('/cart/items', [ShopCartController::class, 'store'])->name('shop.cart.items.store');
        Route::patch('/cart/items/{cartItem}', [ShopCartController::class, 'update'])->name('shop.cart.items.update');
        Route::delete('/cart/items/{cartItem}', [ShopCartController::class, 'destroy'])->name('shop.cart.items.destroy');
        Route::delete('/cart', [ShopCartController::class, 'clear'])->name('shop.cart.clear');
        Route::get('/checkout', [ShopCheckoutController::class, 'show'])->name('shop.checkout.show');
        Route::post('/checkout', [ShopCheckoutController::class, 'store'])->name('shop.checkout.store');
        Route::post('/checkout/summary', [ShopCheckoutController::class, 'summary'])
            ->name('shop.checkout.summary');
        Route::post('/checkout/coupon', [ShopCheckoutCouponController::class, 'store'])
            ->name('shop.checkout.coupon.store');
        Route::delete('/checkout/coupon', [ShopCheckoutCouponController::class, 'destroy'])
            ->name('shop.checkout.coupon.destroy');
        Route::get('/checkout/success/{order}', [ShopCheckoutController::class, 'success'])
            ->name('shop.checkout.success');
        Route::get('/checkout/success/{order}/print', [ShopCheckoutController::class, 'printOrder'])
            ->name('shop.checkout.success.print');
    });

    Route::middleware([
        'storefront.cart',
        ShareStorefrontWishlist::class,
        'auth:customer',
        'customer',
    ])->group(function () {
        Route::get('/wishlist', [ShopWishlistController::class, 'index'])
            ->name('shop.wishlist.index');
        Route::post('/wishlist', [ShopWishlistController::class, 'store'])
            ->name('shop.wishlist.store');
        Route::delete('/wishlist/{product}', [ShopWishlistController::class, 'destroy'])
            ->name('shop.wishlist.destroy');
    });
});

Route::get('/', [LegacyStorefrontRedirectController::class, 'home']);
Route::get('/shop', fn (LegacyStorefrontRedirectController $controller) => $controller->named('shop.products.index'));
Route::get('/cart', fn (LegacyStorefrontRedirectController $controller) => $controller->named('shop.cart.index'));
Route::get('/checkout', fn (LegacyStorefrontRedirectController $controller) => $controller->named('shop.checkout.show'));
Route::get('/login', fn (LegacyStorefrontRedirectController $controller) => $controller->named('customer.login'));
Route::get('/register', fn (LegacyStorefrontRedirectController $controller) => $controller->named('customer.register'));
Route::get('/forgot-password', fn (LegacyStorefrontRedirectController $controller) => $controller->named('customer.password.request'));
Route::get('/reset-password/{token}', fn (LegacyStorefrontRedirectController $controller, string $token) => $controller->named(
    'customer.password.reset',
    ['token' => $token],
    request()->query()
));
Route::get('/products/{key}', fn (LegacyStorefrontRedirectController $controller, string $key) => $controller->entity('product', $key));
Route::get('/categories/{key}', fn (LegacyStorefrontRedirectController $controller, string $key) => $controller->entity('category', $key));
Route::get('/pages/{key}', fn (LegacyStorefrontRedirectController $controller, string $key) => $controller->entity('page', $key));

// Route::get('/', function () {
//     return view('admin/start');
// })->middleware(['auth:admin', 'admin']);

Route::bind('customer', fn (string $value): User => User::customers()
    ->whereKey($value)
    ->firstOrFail());

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [AdminAuthController::class, 'showLogin'])
            ->name('login');
        Route::post('login', [AdminAuthController::class, 'login'])
            ->name('login.store');
    });

    Route::middleware('auth:admin')->group(function () {
        Route::post('logout', [AdminAuthController::class, 'logout'])
            ->name('logout');
    });

    Route::middleware(['auth:admin', 'admin'])->group(function () {
        Route::get('notifications', [AdminNotificationController::class, 'index'])
            ->name('notifications.index');
        Route::patch('notifications/{databaseNotification}/read', [AdminNotificationController::class, 'markAsRead'])
            ->name('notifications.read');
        Route::get('reviews', [AdminProductReviewController::class, 'index'])->name('reviews.index');
        Route::get('reviews/{review}', [AdminProductReviewController::class, 'show'])->name('reviews.show');
        Route::patch('reviews/{review}', [AdminProductReviewController::class, 'update'])->name('reviews.update');
        Route::resource('cms-pages', AdminCmsPageController::class)->only(['index', 'edit', 'update']);
        Route::resource('homepage-banners', AdminHomepageBannerController::class)->except(['show']);
        Route::resource('homepage-services', AdminHomepageServiceController::class)->except(['show']);
        Route::get('refunds/lookups/orders', [AdminRefundController::class, 'orders'])->name('refunds.lookups.orders');
        Route::get('refunds', [AdminRefundController::class, 'index'])->name('refunds.index');
        Route::get('refunds/create', [AdminRefundController::class, 'create'])->name('refunds.create');
        Route::post('refunds', [AdminRefundController::class, 'store'])->name('refunds.store');
        Route::get('refunds/{refund}', [AdminRefundController::class, 'show'])->name('refunds.show');
        Route::get('reports', [AdminReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{report}/export', [AdminReportController::class, 'export'])->name('reports.export');
        Route::get('reports/{report}', [AdminReportController::class, 'show'])->name('reports.show');

        Route::get('customers', [CustomerController::class, 'index'])
            ->name('customers.index');
        Route::get('customers/create', [CustomerController::class, 'create'])
            ->name('customers.create');
        Route::post('customers', [CustomerController::class, 'store'])
            ->name('customers.store');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])
            ->name('customers.show');
        Route::get('customers/{customer}/edit', [CustomerController::class, 'edit'])
            ->name('customers.edit');
        Route::put('customers/{customer}', [CustomerController::class, 'update'])
            ->name('customers.update');
        Route::get('customers/{customer}/password', [CustomerController::class, 'editPassword'])
            ->name('customers.password.edit');
        Route::put('customers/{customer}/password', [CustomerController::class, 'updatePassword'])
            ->name('customers.password.update');
        Route::patch('customers/{customer}/status', [CustomerController::class, 'updateStatus'])
            ->name('customers.status.update');

        Route::get('/settings', [SettingsController::class, 'index'])
            ->name('settings.index');

        Route::put('/settings', [SettingsController::class, 'update'])
            ->name('settings.update');

        Route::get('shipping-methods', [ShippingMethodController::class, 'index'])
            ->name('shipping-methods.index');
        Route::get('shipping-methods/create', [ShippingMethodController::class, 'create'])
            ->name('shipping-methods.create');
        Route::post('shipping-methods', [ShippingMethodController::class, 'store'])
            ->name('shipping-methods.store');
        Route::get('shipping-methods/{shippingMethod}/edit', [ShippingMethodController::class, 'edit'])
            ->name('shipping-methods.edit');
        Route::put('shipping-methods/{shippingMethod}', [ShippingMethodController::class, 'update'])
            ->name('shipping-methods.update');
        Route::patch('shipping-methods/{shippingMethod}/status', [ShippingMethodController::class, 'updateStatus'])
            ->name('shipping-methods.status.update');

        Route::patch('coupons/{coupon}/deactivate', [CouponController::class, 'deactivate'])
            ->name('coupons.deactivate');
        Route::resource('coupons', CouponController::class)
            ->except(['show']);

        Route::resource('attributes', AttributeController::class)
            ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
        Route::resource('categories', CategoryController::class)
            ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

        Route::get('products/{product}/configure', [ProductController::class, 'configure'])
            ->name('products.configure');
        Route::post('products/{product}/configure', [ProductController::class, 'generateConfiguration'])
            ->name('products.configure.store');
        Route::get('products/{product}/variants', [VariantController::class, 'index'])
            ->name('products.variants.index');
        Route::post('products/{product}/variants', [VariantController::class, 'store'])
            ->name('products.variants.store');
        Route::patch('products/{product}/variants/bulk', [VariantController::class, 'bulkUpdate'])
            ->name('products.variants.bulk-update');
        Route::get('products/{product}/variants/{variant}/edit', [VariantController::class, 'edit'])
            ->name('products.variants.edit');
        Route::put('products/{product}/variants/{variant}', [VariantController::class, 'update'])
            ->name('products.variants.update');
        Route::resource('products', ProductController::class)
            ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

        Route::get('orders', [OrderController::class, 'index'])
            ->name('orders.index');
        Route::get('orders/create', [AdminOrderCreationController::class, 'create'])
            ->name('orders.create');
        Route::post('orders', [AdminOrderCreationController::class, 'store'])
            ->name('orders.store');
        Route::post('orders/summary', [AdminOrderCreationController::class, 'summary'])
            ->name('orders.summary');
        Route::get('orders/lookups/customers', [AdminOrderCreationController::class, 'customers'])
            ->name('orders.lookups.customers');
        Route::get('orders/lookups/products', [AdminOrderCreationController::class, 'products'])
            ->name('orders.lookups.products');
        Route::get('orders/lookups/products/{product}/configuration', [AdminOrderCreationController::class, 'configuration'])
            ->name('orders.lookups.products.configuration');
        Route::get('orders/{order}', [OrderController::class, 'show'])
            ->name('orders.show');
        Route::post('orders/{order}/process', [OrderController::class, 'process'])
            ->name('orders.process');
        Route::post('orders/{order}/out-for-delivery', [OrderController::class, 'markOutForDelivery'])
            ->name('orders.out-for-delivery');
        Route::post('orders/{order}/fulfill', [OrderController::class, 'fulfill'])
            ->name('orders.fulfill');
        Route::post('orders/{order}/delivery-failed', [OrderController::class, 'markDeliveryFailed'])
            ->name('orders.delivery-failed');
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])
            ->name('orders.cancel');
        Route::post('orders/{order}/cancellation-requests/{cancellationRequest}/approve', [AdminOrderCancellationRequestController::class, 'approve'])
            ->name('orders.cancellation-requests.approve');
        Route::post('orders/{order}/cancellation-requests/{cancellationRequest}/reject', [AdminOrderCancellationRequestController::class, 'reject'])
            ->name('orders.cancellation-requests.reject');
        Route::post('orders/{order}/payments/paid', [OrderController::class, 'markPaid'])
            ->name('orders.payments.paid');
        Route::post('orders/{order}/payments/failed', [OrderController::class, 'markFailed'])
            ->name('orders.payments.failed');
        Route::post('orders/{order}/payments/retry', [OrderController::class, 'retryPayment'])
            ->name('orders.payments.retry');

        Route::prefix('inventory')->name('inventory.')->group(function () {
            Route::get('/', [InventoryController::class, 'index'])->name('index');
            Route::get('history', [InventoryController::class, 'history'])->name('history');
            Route::get('opening', [InventoryController::class, 'opening'])->name('opening');
            Route::post('opening', [InventoryController::class, 'storeOpening'])->name('opening.store');
            Route::get('receive', [InventoryController::class, 'receive'])->name('receive');
            Route::post('receive', [InventoryController::class, 'storeReceive'])->name('receive.store');
            Route::get('adjustment', [InventoryController::class, 'adjustment'])->name('adjustment');
            Route::post('adjustment', [InventoryController::class, 'storeAdjustment'])->name('adjustment.store');
            Route::get('stock-count', [InventoryController::class, 'stockCount'])->name('stock-count');
            Route::post('stock-count', [InventoryController::class, 'storeStockCount'])->name('stock-count.store');
            Route::patch('products/{product}/low-stock-alert', [InventoryController::class, 'updateLowStockAlert'])
                ->name('low-stock-alert.update');
        });

        Route::prefix('attributes/{attribute}/options')
            ->name('attribute-options.')
            ->group(function () {
                Route::get('/', [AttributeOptionController::class, 'index'])
                    ->name('index');

                Route::post('/save', [AttributeOptionController::class, 'save'])
                    ->name('save');
            });
    });

});

Route::prefix('{locale}')->whereIn('locale', ['en', 'ar'])->group(function () {
    Route::middleware('guest:customer')->group(function () {
        Route::get('forgot-password', [CustomerPasswordResetController::class, 'create'])
            ->name('customer.password.request');
        Route::post('forgot-password', [CustomerPasswordResetController::class, 'store'])
            ->name('customer.password.email');
        Route::get('reset-password/{token}', [CustomerPasswordResetController::class, 'edit'])
            ->name('customer.password.reset');
        Route::post('reset-password', [CustomerPasswordResetController::class, 'update'])
            ->name('customer.password.store');
        Route::get('register', [CustomerAuthController::class, 'showRegistration'])
            ->name('customer.register');
        Route::post('register', [CustomerAuthController::class, 'register'])
            ->name('customer.register.store');
        Route::get('login', [CustomerAuthController::class, 'showLogin'])
            ->name('customer.login');
        Route::post('login', [CustomerAuthController::class, 'login'])
            ->name('customer.login.store');
    });

    Route::middleware(['auth:customer', 'customer'])
        ->prefix('account')
        ->name('shop.account.')
        ->group(function () {
            Route::get('notifications', [ShopAccountNotificationController::class, 'index'])
                ->name('notifications.index');
            Route::patch('notifications/{databaseNotification}/read', [ShopAccountNotificationController::class, 'markAsRead'])
                ->name('notifications.read');
            Route::get('orders', [ShopAccountOrderController::class, 'index'])
                ->name('orders.index');
            Route::get('orders/{order}', [ShopAccountOrderController::class, 'show'])
                ->name('orders.show');
            Route::get('reviews', [ShopAccountReviewController::class, 'index'])->name('reviews.index');
            Route::get('reviews/{review}/edit', [ShopAccountReviewController::class, 'edit'])->name('reviews.edit');
            Route::put('reviews/{review}', [ShopAccountReviewController::class, 'update'])->name('reviews.update');
            Route::post('orders/{order}/cancellation-requests', [ShopOrderCancellationRequestController::class, 'store'])
                ->name('orders.cancellation-requests.store');
        });

    Route::middleware(['auth:customer', 'customer'])->name('customer.')->group(function () {
        Route::post('logout', [CustomerAuthController::class, 'logout'])->name('logout');
        Route::get('account/profile', [CustomerAccountController::class, 'edit'])->name('account.edit');
        Route::put('account/profile', [CustomerAccountController::class, 'update'])->name('account.update');
        Route::get('account/addresses', [CustomerAddressController::class, 'index'])
            ->name('addresses.index');
        Route::get('account/addresses/create', [CustomerAddressController::class, 'create'])
            ->name('addresses.create');
        Route::post('account/addresses', [CustomerAddressController::class, 'store'])
            ->name('addresses.store');
        Route::get('account/addresses/{customerAddress}/edit', [CustomerAddressController::class, 'edit'])
            ->name('addresses.edit');
        Route::put('account/addresses/{customerAddress}', [CustomerAddressController::class, 'update'])
            ->name('addresses.update');
        Route::delete('account/addresses/{customerAddress}', [CustomerAddressController::class, 'destroy'])
            ->name('addresses.destroy');
        Route::patch('account/addresses/{customerAddress}/default-shipping', [CustomerAddressController::class, 'setDefaultShipping'])
            ->name('addresses.default-shipping');
        Route::patch('account/addresses/{customerAddress}/default-billing', [CustomerAddressController::class, 'setDefaultBilling'])
            ->name('addresses.default-billing');
        Route::get('account/password', [CustomerAccountController::class, 'editPassword'])
            ->name('account.password.edit');
        Route::put('account/password', [CustomerAccountController::class, 'updatePassword'])
            ->name('account.password.update');
    });
});
