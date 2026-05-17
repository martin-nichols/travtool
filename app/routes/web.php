<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Home')->name('home');
Route::inertia('/inactive-finder', 'InactiveFinder')->name('inactive-finder');
Route::inertia('/login', 'Auth/Login')->name('login');
