<?php

use App\Http\Controllers\InactiveFinderController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::inertia('/', 'Home')->name('home');
Route::get('/inactive-finder', InactiveFinderController::class)->name('inactive-finder');
Route::inertia('/login', 'Auth/Login')->name('login');

Route::get('/locale/{locale}', function (Request $request, string $locale) {
    abort_unless(in_array($locale, array_keys(config('travtool.locales', [])), true), 404);

    $request->session()->put('locale', $locale);

    $redirect = $request->query('redirect', '/');

    if (! is_string($redirect) || ! str_starts_with($redirect, '/')) {
        $redirect = '/';
    }

    return redirect($redirect);
})->name('locale.switch');
