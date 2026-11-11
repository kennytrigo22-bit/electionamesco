<?php

use App\Models\Ballot;
use App\Models\Election;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Election $election;

    public function mount(Election $election): void
    {
        $this->election = $election;
    }

    protected function tally(Ballot $ballot): array
    {
        $votes = $ballot->votes;
        $candidates = $ballot->candidates->keyBy('id');

        if ($ballot->type === Ballot::TYPE_YESNO) {
            return [
                'Oui' => $votes->where('choice_data', 'yes')->count(),
                'Non' => $votes->where('choice_data', 'no')->count(),
            ];
        }

        if ($ballot->type === Ballot::TYPE_SINGLE_CHOICE) {
            $counts = [];
            foreach ($candidates as $candidate) {
                $counts[$candidate->label] = $votes->filter(
                    fn ($vote) => (string) $vote->choice_data === (string) $candidate->id
                )->count();
            }

            return $counts;
        }

        if ($ballot->type === Ballot::TYPE_MULTIPLE_CHOICE) {
            $counts = [];
            foreach ($candidates as $candidate) {
                $counts[$candidate->label] = $votes->filter(
                    fn ($vote) => in_array((string) $candidate->id, array_map('strval', $vote->choice_data ?? []), true)
                )->count();
            }

            return $counts;
        }

        if ($ballot->type === Ballot::TYPE_RANKED) {
            // Score de Borda simple : points = (nb candidats - rang), rang commençant à 0.
            $counts = [];
            $total = $candidates->count();
            foreach ($candidates as $candidate) {
                $counts[$candidate->label] = 0;
            }
            foreach ($votes as $vote) {
                foreach (($vote->choice_data ?? []) as $rank => $candidateId) {
                    $candidate = $candidates->get((int) $candidateId);
                    if ($candidate) {
                        $counts[$candidate->label] += max($total - $rank, 0);
                    }
                }
            }

            return $counts;
        }

        return [];
    }

    public function with(): array
    {
        $ballots = $this->election->ballots()->with(['candidates', 'votes'])->get();

        return [
            'ballots' => $ballots,
            'results' => $ballots->mapWithKeys(fn ($ballot) => [$ballot->id => $this->tally($ballot)]),
        ];
    }
}; ?>

<div>
    <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8 space-y-8">
        <h1 class="text-xl font-semibold">Résultats — {{ $election->title }}</h1>

        @foreach ($ballots as $ballot)
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                <h2 class="text-lg font-semibold mb-1">{{ $ballot->title }}</h2>
                <p class="text-sm text-gray-500 mb-4">{{ $ballot->votes->count() }} bulletin(s) exprimé(s)</p>

                @php $max = max($results[$ballot->id] ?: [0]); @endphp
                <div class="space-y-3">
                    @foreach ($results[$ballot->id] as $label => $count)
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span>{{ $label }}</span>
                                <span class="font-medium">{{ $count }}</span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-700 rounded h-2">
                                <div class="bg-indigo-600 h-2 rounded" style="width: {{ $max > 0 ? ($count / $max * 100) : 0 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <a href="{{ route('admin.elections.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">&larr; Retour aux élections</a>
    </div>
</div>
