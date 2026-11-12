<?php

use App\Models\Election;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin', ['title' => 'Votes'])] class extends Component
{
    public function with(): array
    {
        return ['elections' => Election::withCount('voters')->whereIn('status', ['open', 'closed', 'scheduled'])->latest()->get()];
    }
}; ?>

<div>
    <x-page-header title="Votes" description="Suivi en direct de la participation par élection." />

    <div class="rounded-xl border border-border bg-card shadow-card divide-y divide-border">
        @forelse ($elections as $election)
            @php
                $cast = $election->voters()->whereNotNull('used_at')->count();
                $participation = $election->voters_count > 0 ? round($cast / $election->voters_count * 100, 1) : 0;
            @endphp
            <div class="p-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="font-semibold">{{ $election->title }}</p>
                    <p class="text-sm text-muted-foreground">{{ $cast }} / {{ $election->voters_count }} votes · {{ $participation }} % de participation</p>
                </div>
                <div class="flex items-center gap-3">
                    <x-status-badge :tone="match($election->status) { 'open' => 'success', 'closed' => 'destructive', default => 'muted' }">{{ $election->statusLabel() }}</x-status-badge>
                    <a href="{{ route('admin.votes.show', $election) }}" wire:navigate class="text-sm font-medium text-primary hover:underline">Suivi détaillé</a>
                    <a href="{{ route('admin.votes.invitations', $election) }}" wire:navigate class="text-sm font-medium text-primary hover:underline">Invitations</a>
                </div>
            </div>
        @empty
            <x-empty-state icon="activity" title="Aucun scrutin" />
        @endforelse
    </div>
</div>
