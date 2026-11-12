<?php

use App\Models\Election;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin', ['title' => 'Suivi du scrutin'])] class extends Component
{
    public Election $election;

    public function mount(Election $election): void
    {
        $this->election = $election;
    }

    public function with(): array
    {
        $totalVoters = $this->election->voters()->count();
        $cast = $this->election->voters()->whereNotNull('used_at')->count();

        $ballots = $this->election->ballots()->withCount('votes')->get();

        return [
            'totalVoters' => $totalVoters,
            'cast' => $cast,
            'participation' => $totalVoters > 0 ? round($cast / $totalVoters * 100, 1) : 0,
            'ballots' => $ballots,
        ];
    }
}; ?>

<div>
    <x-page-header :title="$election->title" description="Suivi en direct — identités et bulletins restent séparés.">
        <x-slot:actions>
            <a href="{{ route('admin.votes.invitations', $election) }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-muted">Invitations</a>
            <a href="{{ route('admin.resultats.show', $election) }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-deep">Résultats</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-6">
        <x-stat-card label="Statut" :value="$election->statusLabel()" icon="vote" tone="primary" />
        <x-stat-card label="Électeurs éligibles" :value="$totalVoters" icon="users" tone="muted" />
        <x-stat-card label="Votes enregistrés" :value="$cast" icon="check" tone="primary" />
        <x-stat-card label="Participation" :value="$participation.' %'" icon="activity" tone="gold" />
    </div>

    <div class="rounded-xl border border-border bg-card p-5 shadow-card">
        <h2 class="text-sm font-semibold mb-4">Répartition par urne</h2>
        <div class="space-y-4">
            @foreach ($ballots as $ballot)
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span>{{ $ballot->title }}</span>
                        <span class="font-medium tabular">{{ $ballot->votes_count }} bulletin(s)</span>
                    </div>
                    <div class="h-2 w-full rounded-full bg-muted">
                        <div class="h-2 rounded-full bg-primary" style="width: {{ $totalVoters > 0 ? min(100, $ballot->votes_count / $totalVoters * 100) : 0 }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
