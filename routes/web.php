<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('dashboard', fn () => redirect()->route('admin.dashboard'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Volt::route('profile', 'profile-page')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/public.php';
