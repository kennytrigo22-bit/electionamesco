<?php

use App\Models\ElectionVoter;
use App\Services\VoteService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.public')] class extends Component
{
    public ?ElectionVoter $voter = null;

    public array $choices = [];

    public bool $invalid = false;

    public bool $done = false;

    public bool $started = false;

    public int $step = 0;

    public bool $reviewing = false;

    public string $errorMessage = '';

    public function mount(string $token): void
    {
        $voter = ElectionVoter::where('token', $token)->with('election.ballots.candidates')->first();

        if (! $voter || ! $voter->election->isOpen() || $voter->hasVoted()) {
            $this->invalid = true;

            return;
        }

        $this->voter = $voter;

        foreach ($voter->election->ballots as $ballot) {
            $this->choices[$ballot->id] = in_array($ballot->type, ['multiple_choice', 'ranked'], true) ? [] : '';
        }
    }

    public function begin(): void
    {
        $this->started = true;
    }

    protected function ballots()
    {
        return $this->voter->election->ballots;
    }

    public function nextStep(): void
    {
        $ballot = $this->ballots()[$this->step];
        $service = app(VoteService::class);

        if (! $service->validateChoice($ballot, $this->choices[$ballot->id] ?? null)) {
            $this->errorMessage = "Merci de compléter l'urne « {$ballot->title} ».";

            return;
        }

        $this->errorMessage = '';

        if ($this->step + 1 < $this->ballots()->count()) {
            $this->step++;
        } else {
            $this->reviewing = true;
        }
    }

    public function prevStep(): void
    {
        $this->errorMessage = '';
        if ($this->reviewing) {
            $this->reviewing = false;

            return;
        }
        $this->step = max(0, $this->step - 1);
    }

    public function submit(): void
    {
        if ($this->invalid) {
            return;
        }

        $service = app(VoteService::class);

        foreach ($this->ballots() as $ballot) {
            if (! $service->validateChoice($ballot, $this->choices[$ballot->id] ?? null)) {
                $this->errorMessage = "Merci de compléter correctement l'urne « {$ballot->title} ».";
                $this->reviewing = false;

                return;
            }
        }

        try {
            $service->castVotes($this->voter, $this->choices);
            $this->done = true;
        } catch (\RuntimeException $e) {
            $this->invalid = true;
            $this->errorMessage = $e->getMessage();
        }
    }

    public function candidateLabel(int $ballotIndex, $candidateId): string
    {
        return $this->ballots()[$ballotIndex]->candidates->firstWhere('id', (int) $candidateId)?->label ?? '';
    }
}; ?>

<div class="max-w-xl mx-auto py-10 px-4">
    @if ($invalid)
        <div class="rounded-2xl border border-border bg-card shadow-card p-8 text-center">
            <span class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-destructive-soft text-destructive"><x-icon name="x" class="size-7" /></span>
            <h1 class="text-xl font-bold mb-2">Lien invalide</h1>
            <p class="text-sm text-muted-foreground">{{ $errorMessage ?: "Ce lien de vote n'existe pas, a déjà été utilisé, ou l'élection n'est pas ouverte." }}</p>
        </div>
    @elseif ($done)
        <div class="rounded-2xl border border-border bg-card shadow-card p-8 text-center">
            <span class="mx-auto mb-4 flex size-16 items-center justify-center rounded-full bg-success-soft text-success"><x-icon name="check" class="size-8" /></span>
            <h1 class="text-xl font-bold mb-2">Votre vote a été enregistré avec succès.</h1>
            <p class="text-sm text-muted-foreground">Merci d'avoir participé à « {{ $voter->election->title }} ». Ce lien de vote ne peut plus être réutilisé.</p>
            <p class="mt-4 text-xs text-muted-foreground">Référence de confirmation</p>
            <p class="font-bold tabular text-primary">{{ strtoupper(substr(md5($voter->id.$voter->election_id.now()->timestamp), 0, 10)) }}</p>
        </div>
    @elseif (! $started)
        <div class="rounded-2xl border border-border bg-card shadow-card p-8 text-center">
            <span class="inline-flex items-center justify-center rounded-lg bg-primary text-white font-extrabold px-3 py-2 text-sm mb-4">AMS</span>
            <h1 class="text-xl font-bold mb-2">{{ $voter->election->title }}</h1>
            <p class="text-sm text-muted-foreground mb-4">{{ $voter->election->description }}</p>
            <div class="rounded-lg bg-muted/60 p-4 text-left text-sm space-y-1 mb-6">
                <p>Bonjour <strong>{{ $voter->name }}</strong>, votre participation a été vérifiée.</p>
                <p class="text-muted-foreground">Vous allez voter pour {{ $voter->election->ballots->count() }} urne(s). Votre vote est anonyme et ne peut être modifié après envoi.</p>
            </div>
            <button type="button" wire:click="begin" class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-deep">Continuer</button>
        </div>
    @else
        <div class="rounded-2xl border border-border bg-card shadow-card p-6">
            @if (! $reviewing)
                <div class="flex items-center gap-1.5 mb-6">
                    @foreach ($voter->election->ballots as $i => $b)
                        <span class="h-1.5 flex-1 rounded-full {{ $i <= $step ? 'bg-primary' : 'bg-muted' }}"></span>
                    @endforeach
                </div>

                @php $ballot = $this->ballots()[$step]; @endphp
                <h2 class="font-semibold mb-1">{{ $ballot->title }}</h2>
                <p class="text-xs text-muted-foreground mb-4">Urne {{ $step + 1 }} / {{ $this->ballots()->count() }}</p>

                @if ($errorMessage)
                    <div class="mb-4 rounded-lg bg-destructive-soft text-destructive text-sm px-3 py-2">{{ $errorMessage }}</div>
                @endif

                <div class="space-y-2 mb-6">
                    @if ($ballot->type === 'yesno')
                        <label class="flex items-center gap-3 rounded-lg border border-border p-3 cursor-pointer has-[:checked]:border-primary has-[:checked]:bg-primary-soft">
                            <input type="radio" wire:model="choices.{{ $ballot->id }}" value="yes"> Oui
                        </label>
                        <label class="flex items-center gap-3 rounded-lg border border-border p-3 cursor-pointer has-[:checked]:border-primary has-[:checked]:bg-primary-soft">
                            <input type="radio" wire:model="choices.{{ $ballot->id }}" value="no"> Non
                        </label>
                    @else
                        @foreach ($ballot->candidates as $candidate)
                            <label class="flex items-center gap-3 rounded-lg border border-border p-3 cursor-pointer has-[:checked]:border-primary has-[:checked]:bg-primary-soft">
                                @if ($ballot->type === 'multiple_choice' || $ballot->type === 'ranked')
                                    <input type="checkbox" wire:model="choices.{{ $ballot->id }}" value="{{ $candidate->id }}">
                                @else
                                    <input type="radio" wire:model="choices.{{ $ballot->id }}" value="{{ $candidate->id }}">
                                @endif
                                @if ($candidate->photo_path)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($candidate->photo_path) }}" class="size-9 rounded-full object-cover">
                                @else
                                    <x-person-avatar :name="$candidate->label" class="size-9" />
                                @endif
                                <span>
                                    <span class="block font-medium">{{ $candidate->label }}</span>
                                    @if ($candidate->description)<span class="block text-xs text-muted-foreground">{{ $candidate->description }}</span>@endif
                                </span>
                            </label>
                        @endforeach
                    @endif
                    @if ($ballot->allow_blank)
                        <label class="flex items-center gap-3 rounded-lg border border-dashed border-border p-3 cursor-pointer text-muted-foreground">
                            <input type="radio" wire:model="choices.{{ $ballot->id }}" value="blank"> Vote blanc
                        </label>
                    @endif
                </div>

                <div class="flex justify-between">
                    <button type="button" wire:click="prevStep" @if($step === 0) disabled @endif class="rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-muted disabled:opacity-40">Précédent</button>
                    <button type="button" wire:click="nextStep" class="rounded-lg bg-primary px-5 py-2 text-sm font-semibold text-white hover:bg-primary-deep">{{ $step + 1 === $this->ballots()->count() ? 'Vérifier mon vote' : 'Suivant' }}</button>
                </div>
            @else
                <h2 class="font-semibold mb-1">Vérifiez votre vote</h2>
                <p class="text-xs text-muted-foreground mb-4">Votre vote ne pourra plus être modifié après confirmation.</p>

                <div class="space-y-3 mb-6">
                    @foreach ($voter->election->ballots as $i => $ballot)
                        @php $choice = $choices[$ballot->id]; @endphp
                        <div class="rounded-lg border border-border p-3 text-sm">
                            <p class="font-medium">{{ $ballot->title }}</p>
                            <p class="text-muted-foreground">
                                @if ($choice === 'blank') Vote blanc
                                @elseif ($ballot->type === 'yesno') {{ $choice === 'yes' ? 'Oui' : 'Non' }}
                                @elseif (is_array($choice)) {{ collect($choice)->map(fn($c) => $this->candidateLabel($i, $c))->implode(', ') }}
                                @else {{ $this->candidateLabel($i, $choice) }}
                                @endif
                            </p>
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-between">
                    <button type="button" wire:click="prevStep" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-muted">Modifier</button>
                    <button type="button" wire:click="submit" class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-deep">Confirmer mon vote</button>
                </div>
            @endif
        </div>
    @endif
</div>
