<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin', ['title' => 'Mon compte'])] class extends Component
{
}; ?>

<div>
    <x-page-header title="Mon compte" description="Gérez vos informations personnelles et votre sécurité." />

    <div class="max-w-xl space-y-6">
        <div class="rounded-xl border border-border bg-card p-5 shadow-card">
            <livewire:profile.update-profile-information-form />
        </div>

        <div class="rounded-xl border border-border bg-card p-5 shadow-card">
            <livewire:profile.update-password-form />
        </div>

        <div class="rounded-xl border border-destructive/30 bg-card p-5 shadow-card">
            <livewire:profile.delete-user-form />
        </div>
    </div>
</div>
