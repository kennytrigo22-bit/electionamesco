<?php

use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin', ['title' => 'Urnes de l\'élection'])] class extends Component
{
    public Election $election;

    public string $newBallotTitle = '';

    public string $newBallotType = 'single_choice';

    public ?int $newBallotMaxChoices = null;

    public array $newCandidateLabel = [];

    public function mount(Election $election): void
    {
        $this->election = $election;
    }

    public function addBallot(): void
    {
        $this->validate([
            'newBallotTitle' => ['required', 'string', 'max:255'],
            'newBallotType' => ['required', 'in:single_choice,multiple_choice,ranked,yesno'],
        ]);

        $this->election->ballots()->create([
            'title' => $this->newBallotTitle,
            'type' => $this->newBallotType,
            'max_choices' => $this->newBallotType === 'multiple_choice' ? $this->newBallotMaxChoices : null,
            'order' => $this->election->ballots()->max('order') + 1,
        ]);

        $this->reset(['newBallotTitle', 'newBallotMaxChoices']);
        $this->newBallotType = 'single_choice';
    }

    public function removeBallot(Ballot $ballot): void
    {
        abort_unless($ballot->election_id === $this->election->id, 403);
        $ballot->delete();
    }

    public function addCandidate(Ballot $ballot): void
    {
        abort_unless($ballot->election_id === $this->election->id, 403);
        $label = trim($this->newCandidateLabel[$ballot->id] ?? '');
        if ($label === '') {
            return;
        }
        $ballot->candidates()->create(['label' => $label, 'order' => $ballot->candidates()->max('order') + 1]);
        $this->newCandidateLabel[$ballot->id] = '';
    }

    public function removeCandidate(int $candidateId): void
    {
        $candidate = Candidate::findOrFail($candidateId);
        abort_unless($candidate->ballot->election_id === $this->election->id, 403);
        $candidate->delete();
    }

    public function updateStatus(string $status): void
    {
        $this->election->update(['status' => $status]);
    }

    public function with(): array
    {
        return ['ballots' => $this->election->ballots()->with('candidates')->get()];
    }
}; ?>

<div>
    <x-page-header :title="$election->title" :description="$election->description">
        <x-slot:actions>
            <select wire:change="updateStatus($event.target.value)" class="rounded-lg border-border text-sm">
                @foreach (\App\Models\Election::STATUS_LABELS as $value => $label)
                    <option value="{{ $value }}" @selected($election->status === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <a href="{{ route('admin.votes.invitations', $election) }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-muted">Invitations</a>
        </x-slot:actions>
    </x-page-header>

    <div class="space-y-5">
        @foreach ($ballots as $ballot)
            <div class="rounded-xl border border-border bg-card p-5 shadow-card">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="font-semibold">{{ $ballot->title }}</h2>
                        <p class="text-xs text-muted-foreground">{{ \App\Models\Ballot::TYPE_LABELS[$ballot->type] }}@if($ballot->max_choices) — max {{ $ballot->max_choices }} choix @endif</p>
                    </div>
                    <button type="button" wire:click="removeBallot({{ $ballot->id }})" wire:confirm="Supprimer cette urne et ses candidats ?" class="rounded-lg bg-destructive-soft px-3 py-1.5 text-xs font-semibold text-destructive hover:brightness-95">Supprimer l'urne</button>
                </div>

                <div class="space-y-2 mb-3">
                    @foreach ($ballot->candidates as $candidate)
                        <div class="flex items-center justify-between rounded-lg border border-border p-2 text-sm">
                            <span class="flex items-center gap-2">
                                @if ($candidate->photo_path)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($candidate->photo_path) }}" class="size-7 rounded-full object-cover">
                                @else
                                    <x-person-avatar :name="$candidate->label" class="size-7" />
                                @endif
                                {{ $candidate->label }}
                            </span>
                            <button type="button" wire:click="removeCandidate({{ $candidate->id }})" class="text-xs text-destructive hover:underline">Retirer</button>
                        </div>
                    @endforeach
                </div>

                <form wire:submit="addCandidate({{ $ballot->id }})" class="flex gap-2">
                    <input type="text" wire:model="newCandidateLabel.{{ $ballot->id }}" placeholder="Nom du candidat / liste / option" class="flex-1 rounded-lg border-border text-sm">
                    <button type="submit" class="rounded-lg border border-border px-3 py-1.5 text-sm font-semibold hover:bg-muted">Ajouter</button>
                </form>
            </div>
        @endforeach

        <div class="rounded-xl border border-border bg-card p-5 shadow-card">
            <h2 class="text-sm font-semibold mb-4">Ajouter une urne</h2>
            <form wire:submit="addBallot" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <input type="text" wire:model="newBallotTitle" placeholder="Ex: Président CEI" class="rounded-lg border-border text-sm">
                    <select wire:model.live="newBallotType" class="rounded-lg border-border text-sm">
                        @foreach (\App\Models\Ballot::TYPE_LABELS as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($newBallotType === 'multiple_choice')
                    <input type="number" min="1" wire:model="newBallotMaxChoices" placeholder="Nombre maximum de choix" class="rounded-lg border-border text-sm w-full sm:w-64">
                @endif
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-deep"><x-icon name="plus" class="size-4" /> Ajouter l'urne</button>
            </form>
        </div>

        <a href="{{ route('admin.elections.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"><x-icon name="arrow-left" class="size-4" /> Retour aux élections</a>
    </div>
</div>
