<?php

use App\Models\ActivityLog;
use App\Models\Election;
use App\Models\Form as CensusForm;
use App\Services\ElectionVoterService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin', ['title' => 'Nouvelle élection'])] class extends Component
{
    public int $step = 1;

    // Étape 1
    public string $title = '';

    public string $description = '';

    // Étape 2
    public array $ballots = [
        ['title' => '', 'type' => 'single_choice', 'max_choices' => null, 'allow_blank' => true, 'allow_abstention' => false, 'candidates' => ['']],
    ];

    // Étape 3
    public array $sources = [];

    public ?int $selectedFormId = null;

    public string $nameFilter = '';

    // Étape 4
    public ?string $opens_at = null;

    public ?string $closes_at = null;

    public string $status = 'draft';

    public function addBallot(): void
    {
        $this->ballots[] = ['title' => '', 'type' => 'single_choice', 'max_choices' => null, 'allow_blank' => true, 'allow_abstention' => false, 'candidates' => ['']];
    }

    public function removeBallot(int $index): void
    {
        unset($this->ballots[$index]);
        $this->ballots = array_values($this->ballots);
    }

    public function addCandidate(int $ballotIndex): void
    {
        $this->ballots[$ballotIndex]['candidates'][] = '';
    }

    public function removeCandidate(int $ballotIndex, int $candidateIndex): void
    {
        unset($this->ballots[$ballotIndex]['candidates'][$candidateIndex]);
        $this->ballots[$ballotIndex]['candidates'] = array_values($this->ballots[$ballotIndex]['candidates']);
    }

    public function addSource(): void
    {
        if (! $this->selectedFormId) {
            return;
        }

        $this->sources[] = ['form_id' => $this->selectedFormId, 'filter' => $this->nameFilter];
        $this->selectedFormId = null;
        $this->nameFilter = '';
    }

    public function removeSource(int $index): void
    {
        unset($this->sources[$index]);
        $this->sources = array_values($this->sources);
    }

    public function next(): void
    {
        if ($this->step === 1) {
            $this->validate(['title' => ['required', 'string', 'max:255']]);
        }
        $this->step = min(4, $this->step + 1);
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function publish(): void
    {
        $this->validate(['title' => ['required', 'string', 'max:255']]);

        $election = Election::create([
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'opens_at' => $this->opens_at,
            'closes_at' => $this->closes_at,
            'created_by' => auth()->id(),
        ]);

        foreach ($this->ballots as $index => $ballot) {
            if (trim($ballot['title']) === '') {
                continue;
            }

            $created = $election->ballots()->create([
                'title' => $ballot['title'],
                'type' => $ballot['type'],
                'max_choices' => $ballot['type'] === 'multiple_choice' ? $ballot['max_choices'] : null,
                'allow_blank' => $ballot['allow_blank'],
                'allow_abstention' => $ballot['allow_abstention'],
                'order' => $index + 1,
            ]);

            foreach ($ballot['candidates'] as $cIndex => $label) {
                if (trim((string) $label) === '') {
                    continue;
                }
                $created->candidates()->create(['label' => $label, 'order' => $cIndex + 1]);
            }
        }

        $voterService = app(ElectionVoterService::class);
        foreach ($this->sources as $source) {
            $form = CensusForm::find($source['form_id']);
            if ($form) {
                $voterService->addFromForm($election, $form, $source['filter'] ?: null);
            }
        }

        ActivityLog::record('election.created', "Élection « {$election->title} » créée", $election);

        $this->redirect(route('admin.elections.builder', $election), navigate: true);
    }

    public function with(): array
    {
        return ['forms' => CensusForm::where('status', '!=', 'draft')->get()];
    }
}; ?>

<div>
    <x-page-header title="Nouvelle élection" description="Assistant de création en 4 étapes." />

    <div class="mb-6 flex items-center gap-2">
        @foreach (['Informations', 'Urnes & candidats', 'Électeurs', 'Planification'] as $i => $label)
            <div class="flex items-center gap-2 {{ $i + 1 < 4 ? 'flex-1' : '' }}">
                <div class="flex size-7 items-center justify-center rounded-full text-xs font-bold {{ $step >= $i + 1 ? 'bg-primary text-white' : 'bg-muted text-muted-foreground' }}">{{ $i + 1 }}</div>
                <span class="text-xs font-medium {{ $step === $i + 1 ? 'text-foreground' : 'text-muted-foreground' }} hidden sm:inline">{{ $label }}</span>
                @if ($i + 1 < 4)
                    <div class="h-px flex-1 bg-border"></div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="rounded-xl border border-border bg-card p-5 shadow-card">
        @if ($step === 1)
            <h2 class="text-sm font-semibold mb-4">Informations générales</h2>
            <div class="space-y-4">
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Titre de l'élection</label>
                    <input type="text" wire:model="title" class="mt-1 block w-full rounded-lg border-border text-sm" placeholder="Ex: Élection du Bureau Exécutif 2026 – 2028">
                    @error('title') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Description</label>
                    <textarea wire:model="description" rows="3" class="mt-1 block w-full rounded-lg border-border text-sm"></textarea>
                </div>
            </div>
        @elseif ($step === 2)
            <h2 class="text-sm font-semibold mb-4">Urnes et candidats</h2>
            <div class="space-y-5">
                @foreach ($ballots as $bIndex => $ballot)
                    <div class="rounded-lg border border-border p-4">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-semibold text-muted-foreground">Urne {{ $bIndex + 1 }}</span>
                            @if (count($ballots) > 1)
                                <button type="button" wire:click="removeBallot({{ $bIndex }})" class="text-xs text-destructive hover:underline">Supprimer</button>
                            @endif
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                            <input type="text" wire:model="ballots.{{ $bIndex }}.title" placeholder="Ex: Président(e)" class="rounded-lg border-border text-sm">
                            <select wire:model="ballots.{{ $bIndex }}.type" class="rounded-lg border-border text-sm">
                                <option value="single_choice">Choix unique</option>
                                <option value="multiple_choice">Choix multiple</option>
                                <option value="ranked">Classement (ranked choice)</option>
                                <option value="yesno">Oui / Non</option>
                            </select>
                        </div>
                        @if ($ballot['type'] === 'multiple_choice')
                            <input type="number" min="1" wire:model="ballots.{{ $bIndex }}.max_choices" placeholder="Nombre maximum de choix" class="rounded-lg border-border text-sm mb-3 w-full sm:w-64">
                        @endif
                        <div class="flex gap-4 mb-3 text-sm">
                            <label class="flex items-center gap-2"><input type="checkbox" wire:model="ballots.{{ $bIndex }}.allow_blank" class="rounded border-border"> Autoriser le vote blanc</label>
                            <label class="flex items-center gap-2"><input type="checkbox" wire:model="ballots.{{ $bIndex }}.allow_abstention" class="rounded border-border"> Autoriser l'abstention</label>
                        </div>
                        @if ($ballot['type'] !== 'yesno')
                            <p class="text-xs font-medium text-muted-foreground mb-2">Candidats / options</p>
                            <div class="space-y-2">
                                @foreach ($ballot['candidates'] as $cIndex => $candidate)
                                    <div class="flex gap-2">
                                        <input type="text" wire:model="ballots.{{ $bIndex }}.candidates.{{ $cIndex }}" placeholder="Nom du candidat" class="flex-1 rounded-lg border-border text-sm">
                                        @if (count($ballot['candidates']) > 1)
                                            <button type="button" wire:click="removeCandidate({{ $bIndex }}, {{ $cIndex }})" class="text-destructive"><x-icon name="x" class="size-4" /></button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" wire:click="addCandidate({{ $bIndex }})" class="mt-2 text-xs font-semibold text-primary">+ Ajouter un candidat</button>
                        @endif
                    </div>
                @endforeach
                <button type="button" wire:click="addBallot" class="inline-flex items-center gap-2 rounded-lg border border-dashed border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-primary-soft"><x-icon name="plus" class="size-4" /> Ajouter une urne</button>
            </div>
        @elseif ($step === 3)
            <h2 class="text-sm font-semibold mb-4">Électeurs</h2>
            <p class="text-xs text-muted-foreground mb-4">Sélectionnez les formulaires sources dont les inscriptions validées constitueront la liste électorale (filtre nom optionnel).</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                <select wire:model="selectedFormId" class="rounded-lg border-border text-sm sm:col-span-1">
                    <option value="">— Formulaire —</option>
                    @foreach ($forms as $form)
                        <option value="{{ $form->id }}">{{ $form->title }}</option>
                    @endforeach
                </select>
                <input type="text" wire:model="nameFilter" placeholder="Filtrer par nom (optionnel)" class="rounded-lg border-border text-sm">
                <button type="button" wire:click="addSource" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-deep">Ajouter la source</button>
            </div>
            <div class="space-y-2">
                @foreach ($sources as $i => $source)
                    @php $f = $forms->firstWhere('id', $source['form_id']); @endphp
                    <div class="flex items-center justify-between rounded-lg border border-border px-3 py-2 text-sm">
                        <span>{{ $f?->title }} @if($source['filter']) (filtre: "{{ $source['filter'] }}") @endif</span>
                        <button type="button" wire:click="removeSource({{ $i }})" class="text-destructive"><x-icon name="x" class="size-4" /></button>
                    </div>
                @endforeach
            </div>
        @elseif ($step === 4)
            <h2 class="text-sm font-semibold mb-4">Planification et publication</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Ouverture</label>
                    <input type="datetime-local" wire:model="opens_at" class="mt-1 block w-full rounded-lg border-border text-sm">
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Fermeture</label>
                    <input type="datetime-local" wire:model="closes_at" class="mt-1 block w-full rounded-lg border-border text-sm">
                </div>
            </div>
            <div class="mb-4">
                <label class="text-xs font-medium text-muted-foreground">Statut initial</label>
                <select wire:model="status" class="mt-1 block w-full sm:w-64 rounded-lg border-border text-sm">
                    <option value="draft">Brouillon</option>
                    <option value="scheduled">Programmée</option>
                    <option value="open">Ouverte immédiatement</option>
                </select>
            </div>
            <div class="rounded-lg bg-muted/60 p-4 text-sm">
                <p class="font-semibold mb-1">Récapitulatif</p>
                <p>{{ $title ?: '—' }} · {{ count(array_filter($ballots, fn($b) => trim($b['title']) !== '')) }} urne(s) · {{ count($sources) }} source(s) d'électeurs</p>
            </div>
        @endif
    </div>

    <div class="mt-5 flex justify-between">
        <button type="button" wire:click="back" @if($step === 1) disabled @endif class="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-muted disabled:opacity-40"><x-icon name="chevron-left" class="size-4" /> Précédent</button>
        @if ($step < 4)
            <button type="button" wire:click="next" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-deep">Suivant <x-icon name="chevron-right" class="size-4" /></button>
        @else
            <button type="button" wire:click="publish" class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-deep"><x-icon name="check" class="size-4" /> Créer l'élection</button>
        @endif
    </div>
</div>
