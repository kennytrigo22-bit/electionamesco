<?php

use Livewire\Volt\Volt;

Volt::route('formulaires/{form:slug}', 'public.form-fill')->name('forms.show');

Volt::route('vote/{token}', 'public.vote')->name('vote.show');
