<?php

use App\Models\ActivityLog;
use App\Models\Election;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin', ['title' => 'Élections'])] class extends Component
{
    public function updateStatus(Election $election, string $status): void
    {
        abort_unless(array_key_exists($status, Election::STATUS_LABELS), 404);

        $election->update(['status' => $status]);
        ActivityLog::record('election.status_changed', "Élection « {$election->title} » : statut changé en « {$election->statusLabel()} »", $election);
    }

    public function deleteElection(Election $election): void
    {
        $title = $election->title;
        $election->delete();
        ActivityLog::record('election.deleted', "Élection « {$title} » supprimée (urnes, candidats et électeurs associés retirés).");
        session()->flash('status', "Élection « {$title} » supprimée.");
    }

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

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-success-soft text-success text-sm px-4 py-2">{{ session('status') }}</div>
    @endif

    <div class="rounded-xl border border-border bg-card shadow-card divide-y divide-border">
        @forelse ($elections as $election)
            <div class="p-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="font-semibold">{{ $election->title }}</p>
                    <p class="text-sm text-muted-foreground">{{ $election->ballots_count }} urne(s) · {{ $election->voters_count }} électeur(s)</p>
                </div>
                <div class="flex items-center gap-3">
                    <x-status-badge :tone="match($election->status) { 'open' => 'success', 'draft' => 'muted', 'scheduled' => 'primary', 'closed' => 'destructive' }">{{ $election->statusLabel() }}</x-status-badge>
                    <select wire:change="updateStatus({{ $election->id }}, $event.target.value)" class="rounded-lg border-border text-xs font-semibold" title="Changer le statut">
                        @foreach (\App\Models\Election::STATUS_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected($election->status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <a href="{{ route('admin.elections.builder', $election) }}" wire:navigate class="text-sm font-medium text-primary hover:underline">Urnes</a>
                    <a href="{{ route('admin.votes.invitations', $election) }}" wire:navigate class="text-sm font-medium text-primary hover:underline">Invitations</a>
                    <a href="{{ route('admin.votes.show', $election) }}" wire:navigate class="text-sm font-medium text-primary hover:underline">Suivi</a>
                    <a href="{{ route('admin.resultats.show', $election) }}" wire:navigate class="text-sm font-medium text-primary hover:underline">Résultats</a>
                    <button type="button" wire:click="deleteElection({{ $election->id }})" wire:confirm="Supprimer définitivement « {{ $election->title }} » ainsi que ses urnes, candidats, électeurs et invitations ? Cette action est irréversible." class="text-sm font-medium text-destructive hover:underline">Supprimer</button>
                </div>
            </div>
        @empty
            <x-empty-state icon="vote" title="Aucune élection" description="Créez votre première élection." />
        @endforelse
    </div>
</div>
