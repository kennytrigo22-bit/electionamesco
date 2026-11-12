<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <h2 class="text-2xl font-bold">Connexion</h2>
    <p class="mt-1 text-sm text-muted-foreground">Espace réservé aux administrateurs et à la commission électorale.</p>

    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form wire:submit="login" class="mt-8 grid gap-4">
        <div class="grid gap-1.5">
            <label for="email" class="text-sm font-medium">Adresse e-mail</label>
            <input wire:model="form.email" id="email" type="email" required autofocus autocomplete="username"
                   class="block w-full rounded-lg border-border text-sm focus:border-primary focus:ring-primary/20">
            <x-input-error :messages="$errors->get('form.email')" />
        </div>

        <div class="grid gap-1.5" x-data="{ show: false }">
            <div class="flex items-center justify-between">
                <label for="password" class="text-sm font-medium">Mot de passe</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" wire:navigate class="text-xs font-medium text-primary hover:underline">Mot de passe oublié ?</a>
                @endif
            </div>
            <div class="relative">
                <input wire:model="form.password" id="password" :type="show ? 'text' : 'password'" required autocomplete="current-password"
                       class="block w-full rounded-lg border-border text-sm pr-10 focus:border-primary focus:ring-primary/20">
                <button type="button" @click="show = !show" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-muted-foreground" :aria-label="show ? 'Masquer' : 'Afficher'">
                    <x-icon x-show="!show" name="eye" class="size-4" />
                    <x-icon x-show="show" name="eye" class="size-4 opacity-50" />
                </button>
            </div>
            <x-input-error :messages="$errors->get('form.password')" />
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-border text-primary focus:ring-primary/20">
            Se souvenir de moi
        </label>

        <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-deep">
            <x-icon name="log-out" class="size-4 rotate-180" /> Se connecter
        </button>
    </form>

    <p class="mt-6 flex items-start gap-2 rounded-lg bg-primary-soft/60 p-3 text-xs text-primary-deep">
        <x-icon name="shield-check" class="mt-0.5 size-4 shrink-0" />
        Connexion chiffrée. Toutes les actions sont consignées dans le journal d'audit.
    </p>
</div>
