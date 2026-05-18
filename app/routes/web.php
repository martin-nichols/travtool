<?php

use App\Http\Controllers\InactiveFinderController;
use App\Http\Controllers\MapBuilderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Home')->name('home');
Route::get('/inactive-finder', InactiveFinderController::class)->name('inactive-finder');
Route::get('/map-builder', MapBuilderController::class)->name('map-builder');
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
