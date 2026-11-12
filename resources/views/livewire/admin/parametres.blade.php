<?php

use App\Models\Setting;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin', ['title' => 'Paramètres'])] class extends Component
{
    public string $session_label = '';

    public string $contact_email = '';

    public string $privacy_notice = '';

    public function mount(): void
    {
        $this->session_label = Setting::get('session_label', 'Session 2026 – 2027');
        $this->contact_email = Setting::get('contact_email', 'contact@amesco-sn.org');
        $this->privacy_notice = Setting::get('privacy_notice', "Vos données sont traitées uniquement dans le cadre du recensement de l'amicale et de la constitution du fichier électoral.");
    }

    public function save(): void
    {
        $this->validate([
            'session_label' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email'],
            'privacy_notice' => ['nullable', 'string'],
        ]);

        Setting::set('session_label', $this->session_label);
        Setting::set('contact_email', $this->contact_email);
        Setting::set('privacy_notice', $this->privacy_notice);

        session()->flash('status', 'Paramètres enregistrés.');
    }
}; ?>

<div>
    <x-page-header title="Paramètres" description="Configuration générale de la plateforme AM.E.S.CO E-VOTE." />

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-success-soft text-success text-sm px-4 py-2">{{ session('status') }}</div>
    @endif

    <div class="rounded-xl border border-border bg-card p-5 shadow-card max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <div>
                <label class="text-xs font-medium text-muted-foreground">Nom de la session / mandat</label>
                <input type="text" wire:model="session_label" class="mt-1 block w-full rounded-lg border-border text-sm">
                <p class="mt-1 text-xs text-muted-foreground">Affiché dans l'en-tête de l'espace administrateur.</p>
            </div>
            <div>
                <label class="text-xs font-medium text-muted-foreground">Email de contact</label>
                <input type="email" wire:model="contact_email" class="mt-1 block w-full rounded-lg border-border text-sm">
            </div>
            <div>
                <label class="text-xs font-medium text-muted-foreground">Message de confidentialité par défaut</label>
                <textarea wire:model="privacy_notice" rows="3" class="mt-1 block w-full rounded-lg border-border text-sm"></textarea>
            </div>
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-deep">Enregistrer</button>
        </form>
    </div>
</div>
