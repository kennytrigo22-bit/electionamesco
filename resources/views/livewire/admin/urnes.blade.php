<?php

use App\Models\Ballot;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin', ['title' => 'Urnes et bulletins'])] class extends Component
{
    public function with(): array
    {
        return ['ballots' => Ballot::with(['election', 'candidates', 'votes'])->get()];
    }
}; ?>

<div>
    <x-page-header title="Urnes et bulletins" description="Vue d'ensemble de toutes les urnes, tous scrutins confondus." />

    <div class="rounded-xl border border-border bg-card shadow-card divide-y divide-border">
        @forelse ($ballots as $ballot)
            <div class="p-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="font-semibold">{{ $ballot->title }}</p>
                    <p class="text-sm text-muted-foreground">{{ $ballot->election->title }} · {{ \App\Models\Ballot::TYPE_LABELS[$ballot->type] }} · {{ $ballot->candidates->count() }} candidat(s) · {{ $ballot->votes->count() }} bulletin(s)</p>
                </div>
                <div class="flex items-center gap-3">
                    <x-status-badge :tone="match($ballot->election->status) { 'open' => 'success', 'closed' => 'destructive', default => 'muted' }">{{ $ballot->election->statusLabel() }}</x-status-badge>
                    <a href="{{ route('admin.elections.builder', $ballot->election) }}" wire:navigate class="text-sm font-medium text-primary hover:underline">Gérer</a>
                    <a href="{{ route('admin.resultats.show', $ballot->election) }}" wire:navigate class="text-sm font-medium text-primary hover:underline">Résultats</a>
                </div>
            </div>
        @empty
            <x-empty-state icon="archive" title="Aucune urne" description="Créez une élection pour configurer des urnes." />
        @endforelse
    </div>
</div>
