<?php

use App\Models\ElectionVoter;
use App\Services\VoteService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public ?ElectionVoter $voter = null;

    public array $choices = [];

    public bool $invalid = false;

    public bool $done = false;

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
            $this->choices[$ballot->id] = $ballot->type === 'multiple_choice' || $ballot->type === 'ranked' ? [] : '';
        }
    }

    public function submit(): void
    {
        if ($this->invalid) {
            return;
        }

        $service = app(VoteService::class);
        $ballots = $this->voter->election->ballots;

        foreach ($ballots as $ballot) {
            $choice = $this->choices[$ballot->id] ?? null;

            if (! $service->validateChoice($ballot, $choice)) {
                $this->errorMessage = "Merci de compléter correctement l'urne « {$ballot->title} ».";

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
}; ?>

<div class="max-w-2xl mx-auto py-10 px-4">
    <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
        @if ($invalid)
            <div class="text-center py-8">
                <h1 class="text-xl font-semibold mb-2">Lien invalide</h1>
                <p class="text-gray-500">{{ $errorMessage ?: "Ce lien de vote n'existe pas, a déjà été utilisé, ou l'élection n'est pas ouverte." }}</p>
            </div>
        @elseif ($done)
            <div class="text-center py-8">
                <h1 class="text-xl font-semibold mb-2">Merci pour votre vote !</h1>
                <p class="text-gray-500">Votre bulletin a été enregistré de façon anonyme.</p>
            </div>
        @else
            <h1 class="text-xl font-semibold mb-1">{{ $voter->election->title }}</h1>
            <p class="text-gray-500 mb-6">Bonjour {{ $voter->name }}, veuillez voter pour chaque urne ci-dessous.</p>

            @if ($errorMessage)
                <div class="bg-red-50 text-red-700 text-sm rounded-md p-3 mb-4">{{ $errorMessage }}</div>
            @endif

            <form wire:submit="submit" class="space-y-8">
                @foreach ($voter->election->ballots as $ballot)
                    <div class="border rounded-lg p-4 dark:border-gray-700">
                        <h2 class="font-semibold mb-3">{{ $ballot->title }}</h2>

                        @if ($ballot->type === 'single_choice')
                            <div class="space-y-2">
                                @foreach ($ballot->candidates as $candidate)
                                    <label class="flex items-center gap-2">
                                        <input type="radio" wire:model="choices.{{ $ballot->id }}" value="{{ $candidate->id }}">
                                        {{ $candidate->label }}
                                    </label>
                                @endforeach
                            </div>
                        @elseif ($ballot->type === 'multiple_choice')
                            <p class="text-sm text-gray-500 mb-2">@if($ballot->max_choices) Choisissez jusqu'à {{ $ballot->max_choices }} option(s). @endif</p>
                            <div class="space-y-2">
                                @foreach ($ballot->candidates as $candidate)
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" wire:model="choices.{{ $ballot->id }}" value="{{ $candidate->id }}">
                                        {{ $candidate->label }}
                                    </label>
                                @endforeach
                            </div>
                        @elseif ($ballot->type === 'ranked')
                            <p class="text-sm text-gray-500 mb-2">Classez les options par ordre de préférence (1 = préféré) en les sélectionnant dans l'ordre.</p>
                            <div class="space-y-2">
                                @foreach ($ballot->candidates as $candidate)
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" wire:model="choices.{{ $ballot->id }}" value="{{ $candidate->id }}">
                                        {{ $candidate->label }}
                                        @php $rank = array_search((string) $candidate->id, $choices[$ballot->id] ?? [], true); @endphp
                                        @if ($rank !== false)
                                            <span class="text-xs bg-indigo-100 text-indigo-800 px-1.5 py-0.5 rounded">#{{ $rank + 1 }}</span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        @elseif ($ballot->type === 'yesno')
                            <div class="space-y-2">
                                <label class="flex items-center gap-2">
                                    <input type="radio" wire:model="choices.{{ $ballot->id }}" value="yes">
                                    Oui
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="radio" wire:model="choices.{{ $ballot->id }}" value="no">
                                    Non
                                </label>
                            </div>
                        @endif
                    </div>
                @endforeach

                <x-primary-button type="submit">Voter</x-primary-button>
            </form>
        @endif
    </div>
</div>
