<?php

use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.admin', ['title' => 'Urnes de l\'élection'])] class extends Component
{
    use WithFileUploads;

    public Election $election;

    public string $newBallotTitle = '';

    public string $newBallotType = 'single_choice';

    public ?int $newBallotMaxChoices = null;

    public array $newCandidateLabel = [];

    public array $newCandidatePhoto = [];

    public array $newCandidateMembers = [];

    public function mount(Election $election): void
    {
        $this->election = $election;
    }

    public function addMemberRow(int $ballotId): void
    {
        $this->newCandidateMembers[$ballotId][] = ['role' => '', 'name' => ''];
    }

    public function removeMemberRow(int $ballotId, int $index): void
    {
        unset($this->newCandidateMembers[$ballotId][$index]);
        $this->newCandidateMembers[$ballotId] = array_values($this->newCandidateMembers[$ballotId] ?? []);
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

        $photoPath = null;
        $photo = $this->newCandidatePhoto[$ballot->id] ?? null;
        if ($photo) {
            $photoPath = $photo->store('candidates', 'public');
        }

        $members = collect($this->newCandidateMembers[$ballot->id] ?? [])
            ->filter(fn ($m) => trim($m['role'] ?? '') !== '' || trim($m['name'] ?? '') !== '')
            ->values()
            ->all();

        $ballot->candidates()->create([
            'label' => $label,
            'photo_path' => $photoPath,
            'members' => $members ?: null,
            'order' => $ballot->candidates()->max('order') + 1,
        ]);

        $this->newCandidateLabel[$ballot->id] = '';
        $this->newCandidatePhoto[$ballot->id] = null;
        $this->newCandidateMembers[$ballot->id] = [];
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
                        <div class="flex items-start justify-between gap-3 rounded-lg border border-border p-3 text-sm">
                            <div class="flex items-start gap-2">
                                @if ($candidate->photo_path)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($candidate->photo_path) }}" class="size-10 rounded-full object-cover">
                                @else
                                    <x-person-avatar :name="$candidate->label" class="size-10" />
                                @endif
                                <div>
                                    <p class="font-medium">{{ $candidate->label }}</p>
                                    @if (! empty($candidate->members))
                                        <ul class="mt-1 space-y-0.5 text-xs text-muted-foreground">
                                            @foreach ($candidate->members as $member)
                                                <li>{{ $member['role'] }} : {{ $member['name'] }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            </div>
                            <button type="button" wire:click="removeCandidate({{ $candidate->id }})" class="shrink-0 text-xs text-destructive hover:underline">Retirer</button>
                        </div>
                    @endforeach
                </div>

                <form wire:submit="addCandidate({{ $ballot->id }})" class="space-y-3 rounded-lg border border-dashed border-border p-3">
                    <div class="flex gap-2">
                        <input type="text" wire:model="newCandidateLabel.{{ $ballot->id }}" placeholder="Nom de la liste / du candidat principal (ex: Président(e))" class="flex-1 rounded-lg border-border text-sm">
                        <input type="file" accept="image/*" wire:model="newCandidatePhoto.{{ $ballot->id }}" class="text-xs w-40">
                    </div>
                    <div class="space-y-2">
                        @foreach ($newCandidateMembers[$ballot->id] ?? [] as $i => $row)
                            <div class="flex gap-2">
                                <input type="text" wire:model="newCandidateMembers.{{ $ballot->id }}.{{ $i }}.role" placeholder="Poste (ex: Secrétaire Général)" class="flex-1 rounded-lg border-border text-sm">
                                <input type="text" wire:model="newCandidateMembers.{{ $ballot->id }}.{{ $i }}.name" placeholder="Nom" class="flex-1 rounded-lg border-border text-sm">
                                <button type="button" wire:click="removeMemberRow({{ $ballot->id }}, {{ $i }})" class="text-destructive"><x-icon name="x" class="size-4" /></button>
                            </div>
                        @endforeach
                        <button type="button" wire:click="addMemberRow({{ $ballot->id }})" class="text-xs font-semibold text-primary hover:underline">+ Ajouter un membre de la liste (SG, secrétaires...)</button>
                    </div>
                    <button type="submit" class="rounded-lg bg-primary px-4 py-1.5 text-sm font-semibold text-white hover:bg-primary-deep">Ajouter la liste</button>
                </form>
            </div>
        @endforeach

        <div class="rounded-xl border border-border bg-card p-5 shadow-card">
            <h2 class="text-sm font-semibold mb-4">Ajouter une urne</h2>
            <form wire:submit="addBallot" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <input type="text" wire:model="newBallotTitle" placeholder="Ex: Bureau Exécutif" class="rounded-lg border-border text-sm">
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
