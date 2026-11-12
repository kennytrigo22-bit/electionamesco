<?php

use App\Models\Election;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin', ['title' => 'Élections'])] class extends Component
{
    public function with(): array
    {
        return ['elections' => Election::withCount(['voters', 'ballots'])->latest()->get()];
    }
}; ?>

<div>
    <x-page-header title="Élections" description="Gérez les scrutins de l'amicale, du brouillon à la clôture.">
        <x-slot:actions>
            <a href="{{ route('admin.elections.nouvelle') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-deep">
                <x-icon name="plus" class="size-4" /> Créer une élection
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="rounded-xl border border-border bg-card shadow-card divide-y divide-border">
        @forelse ($elections as $election)
            <div class="p-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="font-semibold">{{ $election->title }}</p>
                    <p class="text-sm text-muted-foreground">{{ $election->ballots_count }} urne(s) · {{ $election->voters_count }} électeur(s)</p>
                </div>
                <div class="flex items-center gap-3">
                    <x-status-badge :tone="match($election->status) { 'open' => 'success', 'draft' => 'muted', 'scheduled' => 'primary', 'closed' => 'destructive' }">{{ $election->statusLabel() }}</x-status-badge>
                    <a href="{{ route('admin.elections.builder', $election) }}" wire:navigate class="text-sm font-medium text-primary hover:underline">Urnes</a>
                    <a href="{{ route('admin.votes.invitations', $election) }}" wire:navigate class="text-sm font-medium text-primary hover:underline">Invitations</a>
                    <a href="{{ route('admin.votes.show', $election) }}" wire:navigate class="text-sm font-medium text-primary hover:underline">Suivi</a>
                    <a href="{{ route('admin.resultats.show', $election) }}" wire:navigate class="text-sm font-medium text-primary hover:underline">Résultats</a>
                </div>
            </div>
        @empty
            <x-empty-state icon="vote" title="Aucune élection" description="Créez votre première élection." />
        @endforelse
    </div>
</div>
