<?php

use App\Models\Election;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin', ['title' => 'Résultats'])] class extends Component
{
    public function with(): array
    {
        return ['elections' => Election::withCount('voters')->latest()->get()];
    }
}; ?>

<div>
    <x-page-header title="Résultats" description="Consultez les résultats détaillés de chaque élection." />

    <div class="rounded-xl border border-border bg-card shadow-card divide-y divide-border">
        @forelse ($elections as $election)
            <div class="p-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="font-semibold">{{ $election->title }}</p>
                    <p class="text-sm text-muted-foreground">{{ $election->voters_count }} électeur(s)</p>
                </div>
                <div class="flex items-center gap-3">
                    <x-status-badge :tone="match($election->status) { 'open' => 'success', 'closed' => 'destructive', default => 'muted' }">{{ $election->statusLabel() }}</x-status-badge>
                    <a href="{{ route('admin.resultats.show', $election) }}" wire:navigate class="text-sm font-medium text-primary hover:underline">Voir les résultats</a>
                </div>
            </div>
        @empty
            <x-empty-state icon="bar-chart" title="Aucune élection" />
        @endforelse
    </div>
</div>
