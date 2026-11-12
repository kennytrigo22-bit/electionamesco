<?php

use Livewire\Volt\Volt;

Volt::route('/', 'public.index')->name('home');

Volt::route('formulaires/{form:slug}', 'public.form-fill')->name('forms.show');

Volt::route('acces-vote', 'public.vote-access')->name('vote.access');

Volt::route('elections/{election}/candidature', 'public.candidacy-apply')->name('candidacy.apply');

Volt::route('vote/{token}', 'public.vote')->name('vote.show');
