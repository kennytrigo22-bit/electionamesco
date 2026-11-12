<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Volt::route('/', 'admin.dashboard')->name('dashboard');

    Route::middleware('function:be')->group(function () {
        Route::prefix('recensement')->name('forms.')->group(function () {
            Volt::route('/', 'admin.forms.index')->name('index');
            Volt::route('/{form}/editer', 'admin.forms.builder')->name('builder');
            Volt::route('/{form}/parametres', 'admin.forms.settings')->name('settings');
            Volt::route('/{form}/inscriptions', 'admin.forms.submissions')->name('submissions');
        });

        Volt::route('membres', 'admin.membres.index')->name('membres.index');
        Volt::route('fichier-electoral', 'admin.fichier-electoral')->name('fichier-electoral');
    });

    Route::middleware('function:cei')->group(function () {
        Route::prefix('elections')->name('elections.')->group(function () {
            Volt::route('/', 'admin.elections.index')->name('index');
            Volt::route('/nouvelle', 'admin.elections.nouvelle')->name('nouvelle');
            Volt::route('/{election}/editer', 'admin.elections.builder')->name('builder');
        });

        Volt::route('urnes', 'admin.urnes')->name('urnes');

        Route::prefix('candidatures')->name('candidatures.')->group(function () {
            Volt::route('/', 'admin.candidatures.index')->name('index');
        });

        Route::prefix('votes')->name('votes.')->group(function () {
            Volt::route('/', 'admin.votes.index')->name('index');
            Volt::route('/{election}', 'admin.votes.show')->name('show');
            Volt::route('/{election}/invitations', 'admin.votes.invitations')->name('invitations');
        });

        Route::prefix('resultats')->name('resultats.')->group(function () {
            Volt::route('/', 'admin.resultats.index')->name('index');
            Volt::route('/{election}', 'admin.resultats.show')->name('show');
        });
    });

    Volt::route('rapports', 'admin.rapports.index')->name('rapports.index');

    Route::middleware('can:manage-users')->prefix('utilisateurs')->name('users.')->group(function () {
        Volt::route('/', 'admin.users.index')->name('index');
    });

    Volt::route('parametres', 'admin.parametres')->name('parametres');
});
