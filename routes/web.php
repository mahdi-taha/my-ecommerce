<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\AttributeOptionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerAccountController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShippingMethodController;
use App\Http\Controllers\Shop\CartController as ShopCartController;
use App\Http\Controllers\Shop\CheckoutController as ShopCheckoutController;
use App\Http\Controllers\Shop\ProductController as ShopProductController;
use App\Http\Controllers\VariantController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::middleware('storefront.cart')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('shop.home');
    Route::get('/products/{url_key}', [ShopProductController::class, 'show'])
        ->name('shop.products.show');
    Route::get('/cart', [ShopCartController::class, 'index'])->name('shop.cart.index');
    Route::post('/cart/items', [ShopCartController::class, 'store'])->name('shop.cart.items.store');
    Route::patch('/cart/items/{cartItem}', [ShopCartController::class, 'update'])->name('shop.cart.items.update');
    Route::delete('/cart/items/{cartItem}', [ShopCartController::class, 'destroy'])->name('shop.cart.items.destroy');
    Route::delete('/cart', [ShopCartController::class, 'clear'])->name('shop.cart.clear');
    Route::get('/checkout', [ShopCheckoutController::class, 'show'])->name('shop.checkout.show');
    Route::post('/checkout', [ShopCheckoutController::class, 'store'])->name('shop.checkout.store');
    Route::post('/checkout/summary', [ShopCheckoutController::class, 'summary'])
        ->name('shop.checkout.summary');
    Route::get('/checkout/success/{order}', [ShopCheckoutController::class, 'success'])
        ->name('shop.checkout.success');
});
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

Route::middleware('guest:customer')->group(function () {
    Route::get('login', [CustomerAuthController::class, 'showLogin'])
        ->name('customer.login');
    Route::post('login', [CustomerAuthController::class, 'login'])
        ->name('customer.login.store');
});

Route::middleware(['auth:customer', 'customer'])->name('customer.')->group(function () {
    Route::post('logout', [CustomerAuthController::class, 'logout'])->name('logout');
    Route::get('account/profile', [CustomerAccountController::class, 'edit'])->name('account.edit');
    Route::put('account/profile', [CustomerAccountController::class, 'update'])->name('account.update');
    Route::get('account/password', [CustomerAccountController::class, 'editPassword'])
        ->name('account.password.edit');
    Route::put('account/password', [CustomerAccountController::class, 'updatePassword'])
        ->name('account.password.update');
});
