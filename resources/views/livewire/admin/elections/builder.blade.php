<?php

use App\Models\Ballot;
use App\Models\Election;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Election $election;

    public string $newBallotTitle = '';

    public string $newBallotType = 'single_choice';

    public ?int $newBallotMaxChoices = null;

    public array $types = [
        'single_choice' => 'Choix unique',
        'multiple_choice' => 'Choix multiple',
        'ranked' => 'Classement (ranked choice)',
        'yesno' => 'Oui / Non',
    ];

    // candidat en cours d'ajout, par urne
    public array $newCandidateLabel = [];

    public function mount(Election $election): void
    {
        $this->election = $election;
    }

    public function addBallot(): void
    {
        $this->validate([
            'newBallotTitle' => ['required', 'string', 'max:255'],
            'newBallotType' => ['required', 'in:'.implode(',', array_keys($this->types))],
            'newBallotMaxChoices' => ['nullable', 'integer', 'min:1'],
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

        $ballot->candidates()->create([
            'label' => $label,
            'order' => $ballot->candidates()->max('order') + 1,
        ]);

        $this->newCandidateLabel[$ballot->id] = '';
    }

    public function removeCandidate(int $candidateId): void
    {
        $candidate = \App\Models\Candidate::findOrFail($candidateId);
        abort_unless($candidate->ballot->election_id === $this->election->id, 403);
        $candidate->delete();
    }

    public function updateStatus(string $status): void
    {
        $this->election->update(['status' => $status]);
    }

    public function with(): array
    {
        return [
            'ballots' => $this->election->ballots()->with('candidates')->get(),
        ];
    }
}; ?>

<div>
    <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8 space-y-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">{{ $election->title }}</h1>
                <p class="text-sm text-gray-500">{{ $election->description }}</p>
            </div>
            <select wire:change="updateStatus($event.target.value)" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                @foreach (['draft' => 'Brouillon', 'scheduled' => 'Programmée', 'open' => 'Ouverte', 'closed' => 'Clôturée'] as $value => $label)
                    <option value="{{ $value }}" @selected($election->status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        @foreach ($ballots as $ballot)
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ $ballot->title }}</h2>
                        <p class="text-sm text-gray-500">{{ $types[$ballot->type] }}@if($ballot->max_choices) — max {{ $ballot->max_choices }} choix @endif</p>
                    </div>
                    <x-danger-button wire:click="removeBallot({{ $ballot->id }})" wire:confirm="Supprimer cette urne et ses candidats ?">Supprimer l'urne</x-danger-button>
                </div>

                <div class="space-y-2 mb-4">
                    @foreach ($ballot->candidates as $candidate)
                        <div class="flex items-center justify-between border rounded-md p-2 dark:border-gray-700">
                            <span>{{ $candidate->label }}</span>
                            <button type="button" wire:click="removeCandidate({{ $candidate->id }})" class="text-sm text-red-600 hover:underline">Retirer</button>
                        </div>
                    @endforeach
                </div>

                <form wire:submit="addCandidate({{ $ballot->id }})" class="flex gap-2">
                    <x-text-input wire:model="newCandidateLabel.{{ $ballot->id }}" placeholder="Nom du candidat / liste / option" class="flex-1" />
                    <x-secondary-button type="submit">Ajouter</x-secondary-button>
                </form>
            </div>
        @endforeach

        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
            <h2 class="text-lg font-semibold mb-4">Ajouter une urne (poste à élire)</h2>
            <form wire:submit="addBallot" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label value="Titre de l'urne" />
                        <x-text-input wire:model="newBallotTitle" class="block w-full mt-1" placeholder="Ex: Président CEI" />
                        <x-input-error :messages="$errors->get('newBallotTitle')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label value="Type de scrutin" />
                        <select wire:model.live="newBallotType" class="block w-full mt-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if ($newBallotType === 'multiple_choice')
                    <div>
                        <x-input-label value="Nombre maximum de choix" />
                        <x-text-input type="number" min="1" wire:model="newBallotMaxChoices" class="block w-full mt-1" />
                    </div>
                @endif

                <x-primary-button type="submit">Ajouter l'urne</x-primary-button>
            </form>
        </div>

        <a href="{{ route('admin.elections.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">&larr; Retour aux élections</a>
    </div>
</div>
