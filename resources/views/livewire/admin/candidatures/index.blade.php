<?php

use App\Models\ActivityLog;
use App\Models\Candidacy;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin', ['title' => 'Candidatures'])] class extends Component
{
    public string $statusFilter = 'pending';

    public array $ballotChoice = [];

    public function approve(Candidacy $candidacy): void
    {
        $ballotId = $this->ballotChoice[$candidacy->id] ?? $candidacy->election->ballots->first()?->id;

        abort_unless($ballotId, 422);

        $ballot = \App\Models\Ballot::findOrFail($ballotId);
        abort_unless($ballot->election_id === $candidacy->election_id, 403);

        $candidate = $ballot->candidates()->create([
            'label' => $candidacy->name,
            'description' => $candidacy->program,
            'photo_path' => $candidacy->photo_path,
            'order' => $ballot->candidates()->max('order') + 1,
        ]);

        $candidacy->update([
            'ballot_id' => $ballot->id,
            'status' => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        ActivityLog::record('candidacy.approved', "Candidature de « {$candidacy->name} » approuvée pour « {$ballot->title} »", $candidacy);
    }

    public function reject(Candidacy $candidacy): void
    {
        $candidacy->update(['status' => 'rejected', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
        ActivityLog::record('candidacy.rejected', "Candidature de « {$candidacy->name} » rejetée", $candidacy);
    }

    public function with(): array
    {
        $query = Candidacy::with(['election.ballots'])->latest('submitted_at');
        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        return ['candidacies' => $query->get()];
    }
}; ?>

<div>
    <x-page-header title="Candidatures" description="Examinez les candidatures soumises pour les élections à venir." />

    <select wire:model.live="statusFilter" class="rounded-lg border-border text-sm mb-4">
        <option value="pending">En attente</option>
        <option value="approved">Approuvées</option>
        <option value="rejected">Rejetées</option>
        <option value="all">Toutes</option>
    </select>

    <div class="rounded-xl border border-border bg-card shadow-card divide-y divide-border">
        @forelse ($candidacies as $candidacy)
            <div class="p-4 flex flex-wrap items-start justify-between gap-4">
                <div class="flex gap-3">
                    @if ($candidacy->photo_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($candidacy->photo_path) }}" class="size-12 rounded-full object-cover">
                    @else
                        <x-person-avatar :name="$candidacy->name" class="size-12" />
                    @endif
                    <div>
                        <p class="font-semibold">{{ $candidacy->name }}</p>
                        <p class="text-xs text-muted-foreground">{{ $candidacy->election->title }} · {{ $candidacy->institution }}</p>
                        <p class="mt-1 text-sm text-muted-foreground max-w-md">{{ $candidacy->program }}</p>
                    </div>
                </div>
                <div class="flex flex-col items-end gap-2">
                    <x-status-badge :tone="match($candidacy->status) { 'approved' => 'success', 'rejected' => 'destructive', default => 'gold' }">
                        {{ ['pending' => 'En attente', 'approved' => 'Approuvée', 'rejected' => 'Rejetée'][$candidacy->status] }}
                    </x-status-badge>
                    @if ($candidacy->status === 'pending')
                        <div class="flex items-center gap-2">
                            <select wire:model="ballotChoice.{{ $candidacy->id }}" class="rounded-lg border-border text-xs">
                                @foreach ($candidacy->election->ballots as $ballot)
                                    <option value="{{ $ballot->id }}">{{ $ballot->title }}</option>
                                @endforeach
                            </select>
                            <button type="button" wire:click="approve({{ $candidacy->id }})" class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-deep">Approuver</button>
                            <button type="button" wire:click="reject({{ $candidacy->id }})" class="rounded-lg bg-destructive px-3 py-1.5 text-xs font-semibold text-white hover:brightness-95">Rejeter</button>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <x-empty-state icon="user-check" title="Aucune candidature" description="Les candidatures soumises publiquement apparaîtront ici." />
        @endforelse
    </div>
</div>
