<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\InactiveFinderController;
use App\Http\Controllers\MapBuilderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Home')->name('home');
Route::get('/inactive-finder', InactiveFinderController::class)->name('inactive-finder');
Route::get('/map-builder', MapBuilderController::class)->name('map-builder');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/locale/{locale}', function (Request $request, string $locale) {
    abort_unless(in_array($locale, array_keys(config('travtool.locales', [])), true), 404);

    $request->session()->put('locale', $locale);

    $redirect = $request->query('redirect', '/');

    if (! is_string($redirect) || ! str_starts_with($redirect, '/')) {
        $redirect = '/';
    }

    return redirect($redirect);
})->name('locale.switch');
