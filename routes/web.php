<?php

use Illuminate\Support\Facades\Route;

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

Route::livewire('/dashboard', 'pages::backend.dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');
Route::livewire('/categories', 'pages::backend.categories.index')
    ->middleware(['auth', 'verified'])
    ->name('categories');

require __DIR__.'/settings.php';
