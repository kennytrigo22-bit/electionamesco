<?php

use App\Models\ActivityLog;
use App\Models\Ballot;
use App\Models\Election;
use Illuminate\Support\Facades\Response;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin', ['title' => 'Résultats'])] class extends Component
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
        $blank = $votes->where('choice_data', 'blank')->count();
        $abstain = $votes->where('choice_data', 'abstain')->count();

        if ($ballot->type === Ballot::TYPE_YESNO) {
            $counts = ['Oui' => $votes->where('choice_data', 'yes')->count(), 'Non' => $votes->where('choice_data', 'no')->count()];
        } elseif ($ballot->type === Ballot::TYPE_SINGLE_CHOICE) {
            $counts = [];
            foreach ($candidates as $candidate) {
                $counts[$candidate->label] = $votes->filter(fn ($v) => (string) $v->choice_data === (string) $candidate->id)->count();
            }
        } elseif ($ballot->type === Ballot::TYPE_MULTIPLE_CHOICE) {
            $counts = [];
            foreach ($candidates as $candidate) {
                $counts[$candidate->label] = $votes->filter(fn ($v) => is_array($v->choice_data) && in_array((string) $candidate->id, array_map('strval', $v->choice_data), true))->count();
            }
        } elseif ($ballot->type === Ballot::TYPE_RANKED) {
            $counts = [];
            $total = $candidates->count();
            foreach ($candidates as $candidate) {
                $counts[$candidate->label] = 0;
            }
            foreach ($votes as $vote) {
                if (! is_array($vote->choice_data)) {
                    continue;
                }
                foreach ($vote->choice_data as $rank => $candidateId) {
                    $candidate = $candidates->get((int) $candidateId);
                    if ($candidate) {
                        $counts[$candidate->label] += max($total - $rank, 0);
                    }
                }
            }
        } else {
            $counts = [];
        }

        arsort($counts);
        $max = $counts ? max($counts) : 0;
        $winners = array_keys(array_filter($counts, fn ($v) => $v === $max && $max > 0));

        return [
            'counts' => $counts,
            'expressed' => $votes->count() - $blank - $abstain,
            'blank' => $blank,
            'abstain' => $abstain,
            'tie' => count($winners) > 1,
            'winner' => count($winners) === 1 ? $winners[0] : null,
            'membersByLabel' => $candidates->mapWithKeys(fn ($c) => [$c->label => $c->members ?? []]),
        ];
    }

    public function publish(): void
    {
        $this->election->update(['status' => 'closed']);
        ActivityLog::record('results.published', "Résultats publiés pour « {$this->election->title} »", $this->election);
    }

    public function exportCsv()
    {
        $ballots = $this->election->ballots()->with(['candidates', 'votes'])->get();

        return Response::streamDownload(function () use ($ballots) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Urne', 'Option', 'Voix']);
            foreach ($ballots as $ballot) {
                foreach ($this->tally($ballot)['counts'] as $label => $count) {
                    fputcsv($out, [$ballot->title, $label, $count]);
                }
            }
            fclose($out);
        }, 'resultats-election-'.$this->election->id.'.csv');
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
    <x-page-header :title="'Résultats — '.$election->title">
        <x-slot:actions>
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-muted no-print">Imprimer / PDF</button>
            <button type="button" wire:click="exportCsv" class="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-muted no-print">Exporter CSV</button>
            @if ($election->status !== 'closed')
                <button type="button" wire:click="publish" wire:confirm="Clôturer l'élection et publier les résultats ?" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-deep no-print">Publier les résultats</button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="space-y-5">
        @foreach ($ballots as $ballot)
            @php $r = $results[$ballot->id]; @endphp
            <div class="rounded-xl border border-border bg-card p-5 shadow-card">
                <div class="flex items-start justify-between mb-1">
                    <h2 class="text-lg font-semibold">{{ $ballot->title }}</h2>
                    @if ($r['tie'])
                        <x-status-badge tone="gold">Égalité détectée</x-status-badge>
                    @elseif ($r['winner'])
                        <x-status-badge tone="success">{{ $r['winner'] }}</x-status-badge>
                    @endif
                </div>
                <p class="text-sm text-muted-foreground mb-4">{{ $r['expressed'] }} exprimé(s) · {{ $r['blank'] }} blanc(s) · {{ $r['abstain'] }} abstention(s)</p>

                @if ($r['winner'] && ! empty($r['membersByLabel'][$r['winner']]))
                    <div class="mb-4 rounded-lg bg-success-soft p-3 text-sm">
                        <p class="font-semibold text-success mb-1">Liste élue — {{ $r['winner'] }}</p>
                        <ul class="space-y-0.5 text-muted-foreground">
                            @foreach ($r['membersByLabel'][$r['winner']] as $member)
                                <li>{{ $member['role'] }} : {{ $member['name'] }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @php $max = $r['counts'] ? max($r['counts']) : 0; @endphp
                <div class="space-y-3">
                    @forelse ($r['counts'] as $label => $count)
                        <div>
                            <div class="flex justify-between text-sm mb-1"><span>{{ $label }}</span><span class="font-semibold tabular">{{ $count }} @if($r['expressed'] > 0) ({{ round($count / max($r['expressed'],1) * 100, 1) }} %) @endif</span></div>
                            <div class="h-2 w-full rounded-full bg-muted"><div class="h-2 rounded-full bg-primary" style="width: {{ $max > 0 ? $count / $max * 100 : 0 }}%"></div></div>
                        </div>
                    @empty
                        <p class="text-sm text-muted-foreground">Aucun bulletin pour l'instant.</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
