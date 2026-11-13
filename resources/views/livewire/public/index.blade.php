<?php

use App\Models\Election;
use App\Models\Form as CensusForm;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.public')] class extends Component
{
    public function with(): array
    {
        return [
            'openForms' => CensusForm::where('status', 'open')->get(),
            'activeElection' => Election::where('status', 'open')->latest()->first(),
            'upcomingElections' => Election::where('status', 'scheduled')->get(),
        ];
    }
}; ?>

<div>
    <section class="bg-gradient-to-br from-primary-deep to-primary text-white">
        <div class="mx-auto max-w-5xl px-4 py-16 text-center">
            <span class="inline-flex items-center justify-center rounded-lg bg-white/15 px-3 py-1.5 text-xs font-semibold tracking-wide uppercase backdrop-blur">AM.E.S.CO E-VOTE</span>
            <h1 class="mt-4 text-3xl sm:text-4xl font-extrabold leading-tight">Votre voix compte.<br>Votre engagement construit notre avenir.</h1>
            <p class="mt-4 max-w-xl mx-auto text-white/85">La plateforme numérique de recensement et de vote de l'Amicale des Élèves, Étudiants et Stagiaires Congolais au Sénégal.</p>
            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                @if ($openForms->isNotEmpty())
                    <a href="{{ route('forms.show', $openForms->first()) }}" wire:navigate class="rounded-lg bg-gold px-5 py-2.5 text-sm font-semibold text-gold-foreground hover:brightness-95">Participer au recensement</a>
                @endif
                <a href="{{ route('vote.access') }}" wire:navigate class="rounded-lg bg-white/15 px-5 py-2.5 text-sm font-semibold backdrop-blur hover:bg-white/25">Accéder à une élection</a>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-5xl px-4 py-12">
        @if ($activeElection)
            <div class="rounded-2xl border border-primary/20 bg-primary-soft p-6 flex flex-wrap items-center justify-between gap-4 mb-10">
                <div>
                    <x-status-badge tone="success">Scrutin en cours</x-status-badge>
                    <p class="mt-2 font-semibold">{{ $activeElection->title }}</p>
                    <p class="text-sm text-muted-foreground">Ouvert jusqu'au {{ $activeElection->closes_at?->format('d/m/Y H:i') ?? '—' }}</p>
                </div>
                <a href="{{ route('vote.access') }}" wire:navigate class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-deep">Voter maintenant</a>
            </div>
        @endif

        @if ($upcomingElections->isNotEmpty())
            <div class="mb-10">
                <h2 class="text-lg font-semibold mb-3">Élections à venir</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach ($upcomingElections as $election)
                        <div class="rounded-xl border border-border bg-card p-4 shadow-card">
                            <p class="font-semibold">{{ $election->title }}</p>
                            <p class="text-sm text-muted-foreground">Ouverture le {{ $election->opens_at?->format('d/m/Y') ?? '—' }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-12">
            <div class="text-center">
                <span class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-primary-soft text-primary-deep"><x-icon name="clipboard" class="size-6" /></span>
                <p class="font-semibold">1. Inscrivez-vous</p>
                <p class="text-sm text-muted-foreground">Remplissez le formulaire de recensement de l'amicale.</p>
            </div>
            <div class="text-center">
                <span class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-primary-soft text-primary-deep"><x-icon name="book-user" class="size-6" /></span>
                <p class="font-semibold">2. Soyez validé</p>
                <p class="text-sm text-muted-foreground">La commission vérifie et valide votre inscription.</p>
            </div>
            <div class="text-center">
                <span class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-primary-soft text-primary-deep"><x-icon name="vote" class="size-6" /></span>
                <p class="font-semibold">3. Votez</p>
                <p class="text-sm text-muted-foreground">Recevez votre lien unique et votez en toute confidentialité.</p>
            </div>
        </div>

    </section>

    <section class="bg-sidebar text-white">
        <div class="mx-auto max-w-5xl px-4 py-12">
            <h2 class="text-center text-lg font-bold">Sécurité et transparence</h2>
            <div class="mt-8 grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div class="text-center">
                    <span class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-white/10"><x-icon name="lock" class="size-6" /></span>
                    <p class="font-semibold">Vote secret</p>
                    <p class="mt-1 text-sm text-white/70">Identités et bulletins sont stockés séparément : personne ne peut relier un vote à son auteur.</p>
                </div>
                <div class="text-center">
                    <span class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-white/10"><x-icon name="user-check" class="size-6" /></span>
                    <p class="font-semibold">Un électeur, un vote</p>
                    <p class="mt-1 text-sm text-white/70">Chaque lien de vote est unique et à usage unique, invalidé dès son utilisation.</p>
                </div>
                <div class="text-center">
                    <span class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-white/10"><x-icon name="shield-check" class="size-6" /></span>
                    <p class="font-semibold">Transparence</p>
                    <p class="mt-1 text-sm text-white/70">Chaque action administrative est consignée dans un journal d'audit public à la commission électorale.</p>
                </div>
            </div>
        </div>
    </section>
</div>
