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

//Route::view('/', 'main')
//    ->name('home');

Route::livewire('/', 'pages::main')->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

require __DIR__.'/settings.php';
