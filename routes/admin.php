<?php

use App\Models\Election;
use App\Models\Form;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::middleware('function:be')->prefix('formulaires')->name('forms.')->group(function () {
        Volt::route('/', 'admin.forms.index')->name('index');
        Volt::route('/{form}/editer', 'admin.forms.builder')->name('builder');
        Volt::route('/{form}/inscriptions', 'admin.forms.submissions')->name('submissions');
    });

    Route::middleware('function:cei')->prefix('elections')->name('elections.')->group(function () {
        Volt::route('/', 'admin.elections.index')->name('index');
        Volt::route('/{election}/editer', 'admin.elections.builder')->name('builder');
        Volt::route('/{election}/electeurs', 'admin.elections.voters')->name('voters');
        Volt::route('/{election}/resultats', 'admin.elections.results')->name('results');
    });

    Route::middleware('can:manage-users')->prefix('administrateurs')->name('users.')->group(function () {
        Volt::route('/', 'admin.users.index')->name('index');
    });
});
