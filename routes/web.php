<?php

use App\Http\Controllers\TopupProofController;
use Illuminate\Support\Facades\Route;

Route::livewire('/404', 'pages::errors.404')->name('404');

Route::get('language/{locale}', function (string $locale) {
    if (! in_array($locale, ['en', 'ar'])) {
        abort(400);
    }

    session()->put('locale', $locale);
    session()->save();

    app()->setLocale($locale);

    return redirect()->back();
})->name('language.switch');

// Route::view('/', 'main')
//    ->name('home');

Route::livewire('/', 'pages::frontend.main')->name('home');
Route::livewire('/cart', 'pages::frontend.cart')->name('cart');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/wallet', 'pages::frontend.wallet')->name('wallet');
    Route::livewire('/loyalty', 'pages::frontend.loyalty')->name('loyalty');
    Route::livewire('/orders', 'pages::frontend.orders')->name('orders.index');
    Route::livewire('/orders/{order:order_number}', 'pages::frontend.order-details')->name('orders.show');
    Route::livewire('/notifications', 'pages::frontend.notifications')->name('notifications.index');
    Route::get('/topup-proofs/{proof}', [TopupProofController::class, 'show'])->name('topup-proofs.show');
});

Route::middleware(['auth', 'verified', 'backend'])->group(function () {
    Route::livewire('/dashboard', 'pages::backend.dashboard')->name('dashboard');
    Route::livewire('/categories', 'pages::backend.categories.index')->name('categories');
    Route::livewire('/packages', 'pages::backend.packages.index')->name('packages');
    Route::livewire('/products', 'pages::backend.products.index')->name('products');
    Route::livewire('/pricing-rules', 'pages::backend.pricing-rules.index')->name('pricing-rules');
    Route::livewire('/loyalty-tiers', 'pages::backend.loyalty-tiers.index')->name('loyalty-tiers');
    Route::livewire('/admin/orders', 'pages::backend.orders.index')->name('admin.orders.index');
    Route::livewire('/admin/orders/{order}', 'pages::backend.orders.show')->name('admin.orders.show');
    Route::livewire('/admin/activities', 'pages::backend.activities.index')->name('admin.activities.index');
    Route::livewire('/admin/users', 'pages::backend.users.index')->name('admin.users.index');
    Route::livewire('/admin/users/{user}', 'pages::backend.users.show')->name('admin.users.show');
    Route::livewire('/fulfillments', 'pages::backend.fulfillments.index')->name('fulfillments');
    Route::livewire('/refunds', 'pages::backend.refunds.index')->name('refunds');
    Route::livewire('/topups', 'pages::backend.topups.index')->name('topups');
    Route::livewire('/customer-funds', 'pages::backend.customer-funds.index')->name('customer-funds');
    Route::livewire('/settlements', 'pages::backend.settlements.index')->name('settlements');
    Route::livewire('/admin/notifications', 'pages::backend.notifications.index')->name('admin.notifications.index');
});

require __DIR__.'/settings.php';
