<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InactiveFinderController;
use App\Http\Controllers\MapBuilderController;
use App\Http\Controllers\UserMapController;
use App\Http\Controllers\UserWorldController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
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

Route::middleware('auth')->group(function (): void {
    Route::post('/my-worlds', [UserWorldController::class, 'store'])->name('my-worlds.store');
    Route::patch('/my-worlds/selected', [UserWorldController::class, 'select'])->name('my-worlds.select');
    Route::delete('/my-worlds/{worldKey}', [UserWorldController::class, 'destroy'])->name('my-worlds.destroy');
    Route::post('/my-maps', [UserMapController::class, 'store'])->name('my-maps.store');
    Route::delete('/my-maps/{userMap}', [UserMapController::class, 'destroy'])->name('my-maps.destroy');
});

Route::get('/locale/{locale}', function (Request $request, string $locale) {
    abort_unless(in_array($locale, array_keys(config('travtool.locales', [])), true), 404);

    $request->session()->put('locale', $locale);

    $redirect = $request->query('redirect', '/');

    if (! is_string($redirect) || ! str_starts_with($redirect, '/')) {
        $redirect = '/';
    }

    return redirect($redirect);
})->name('locale.switch');
