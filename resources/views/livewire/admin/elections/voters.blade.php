<?php

use App\Jobs\SendVoteLinkEmail;
use App\Models\Election;
use App\Models\ElectionVoter;
use App\Models\Form;
use App\Services\ElectionVoterService;
use Illuminate\Support\Facades\Response;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Election $election;

    public ?int $selectedFormId = null;

    public string $nameFilter = '';

    public string $search = '';

    public function mount(Election $election): void
    {
        $this->election = $election;
    }

    public function addSource(): void
    {
        $this->validate([
            'selectedFormId' => ['required', 'exists:forms,id'],
        ]);

        $form = Form::findOrFail($this->selectedFormId);

        $added = app(ElectionVoterService::class)->addFromForm(
            $this->election,
            $form,
            $this->nameFilter !== '' ? $this->nameFilter : null
        );

        session()->flash('status', "{$added} électeur(s) ajouté(s) à la liste électorale.");

        $this->reset(['selectedFormId', 'nameFilter']);
    }

    public function removeVoter(ElectionVoter $voter): void
    {
        abort_unless($voter->election_id === $this->election->id, 403);
        $voter->delete();
    }

    public function sendEmails(): void
    {
        $voters = $this->election->voters()->whereNull('email_sent_at')->get();

        foreach ($voters as $voter) {
            SendVoteLinkEmail::dispatch($voter);
        }

        session()->flash('status', "Envoi de {$voters->count()} email(s) en cours.");
    }

    public function exportCsv()
    {
        $voters = $this->election->voters()->get();

        $filename = 'electeurs-election-'.$this->election->id.'.csv';

        return Response::streamDownload(function () use ($voters) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Nom', 'Email', 'Téléphone', 'Lien de vote unique', 'A voté']);
            foreach ($voters as $voter) {
                fputcsv($out, [
                    $voter->name,
                    $voter->email,
                    $voter->phone,
                    $voter->voteUrl(),
                    $voter->hasVoted() ? 'Oui' : 'Non',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function with(): array
    {
        $voters = $this->election->voters()->orderBy('name');

        if ($this->search !== '') {
            $voters->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            });
        }

        return [
            'forms' => Form::where('status', '!=', 'draft')->get(),
            'voters' => $voters->get(),
            'sources' => $this->election->sources()->with('form')->get(),
        ];
    }
}; ?>

<div>
    <div class="max-w-5xl mx-auto py-10 sm:px-6 lg:px-8 space-y-8">
        <div>
            <h1 class="text-xl font-semibold">Liste électorale — {{ $election->title }}</h1>
        </div>

        @if (session('status'))
            <div class="bg-green-50 text-green-800 text-sm rounded-md p-3">{{ session('status') }}</div>
        @endif

        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
            <h2 class="text-lg font-semibold mb-4">Ajouter des électeurs depuis un formulaire</h2>
            <form wire:submit="addSource" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                <div>
                    <x-input-label value="Formulaire source" />
                    <select wire:model="selectedFormId" class="block w-full mt-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                        <option value="">— Choisir —</option>
                        @foreach ($forms as $form)
                            <option value="{{ $form->id }}">{{ $form->title }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('selectedFormId')" class="mt-2" />
                </div>
                <div>
                    <x-input-label value="Filtrer par nom (optionnel)" />
                    <x-text-input wire:model="nameFilter" class="block w-full mt-1" placeholder="Ex: Diallo" />
                </div>
                <x-primary-button type="submit">Ajouter les inscriptions validées</x-primary-button>
            </form>

            @if ($sources->isNotEmpty())
                <div class="mt-4 text-sm text-gray-500">
                    Sources déjà ajoutées :
                    @foreach ($sources as $source)
                        <span class="inline-flex items-center gap-1 bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded mr-2">
                            {{ $source->form->title }}
                            @if ($source->filters['name'] ?? null) (filtre: "{{ $source->filters['name'] }}") @endif
                        </span>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
            <div class="flex items-center justify-between mb-4 gap-4 flex-wrap">
                <h2 class="text-lg font-semibold">Liste électorale ({{ $voters->count() }})</h2>
                <div class="flex items-center gap-2">
                    <x-text-input wire:model.live.debounce.300ms="search" placeholder="Rechercher..." />
                    <x-secondary-button wire:click="sendEmails" wire:confirm="Envoyer le lien de vote par email à tous les électeurs qui ne l'ont pas encore reçu ?">
                        Envoyer les liens par email
                    </x-secondary-button>
                    <x-secondary-button wire:click="exportCsv">Exporter CSV</x-secondary-button>
                </div>
            </div>

            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse ($voters as $voter)
                    <div class="py-3 flex items-center justify-between gap-4">
                        <div>
                            <p class="font-medium">{{ $voter->name }}</p>
                            <p class="text-sm text-gray-500">
                                {{ $voter->email }}
                                @if ($voter->email_sent_at) — <span class="text-green-600">email envoyé</span> @endif
                                @if ($voter->hasVoted()) — <span class="text-indigo-600">a voté</span> @endif
                            </p>
                        </div>
                        <x-danger-button wire:click="removeVoter({{ $voter->id }})" wire:confirm="Retirer cet électeur ?">Retirer</x-danger-button>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 py-4">Aucun électeur pour le moment.</p>
                @endforelse
            </div>
        </div>

        <a href="{{ route('admin.elections.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">&larr; Retour aux élections</a>
    </div>
</div>
