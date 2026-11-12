<?php

use App\Jobs\SendVoteLinkEmail;
use App\Models\ActivityLog;
use App\Models\Election;
use App\Models\ElectionVoter;
use App\Models\FormField;
use App\Models\Form as CensusForm;
use App\Services\ElectionVoterService;
use App\Support\ExcelExport;
use Illuminate\Support\Facades\Response;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin', ['title' => 'Invitations de vote'])] class extends Component
{
    public Election $election;

    public ?int $selectedFormId = null;

    public string $nameFilter = '';

    public string $search = '';

    public ?int $assignBallotId = null;

    public ?int $criterionFieldId = null;

    public string $criterionValue = '';

    public array $selectedForAssign = [];

    public function mount(Election $election): void
    {
        $this->election = $election;
    }

    public function updatedCriterionFieldId(): void
    {
        $this->criterionValue = '';
    }

    public function unassignedQuery()
    {
        $query = $this->election->voters()->whereNull('ballot_id');

        if ($this->criterionFieldId && $this->criterionValue !== '') {
            $query->whereHas(
                'submission.values',
                fn ($q) => $q->where('form_field_id', $this->criterionFieldId)->where('value', $this->criterionValue)
            );
        }

        return $query;
    }

    public function assignToBallot(): void
    {
        $this->validate(['assignBallotId' => ['required', 'exists:ballots,id']]);

        $ballot = \App\Models\Ballot::findOrFail($this->assignBallotId);
        abort_unless($ballot->election_id === $this->election->id, 403);

        if ($this->selectedForAssign) {
            $count = $this->election->voters()
                ->whereIn('id', $this->selectedForAssign)
                ->whereNull('ballot_id')
                ->update(['ballot_id' => $ballot->id]);
        } else {
            $count = $this->unassignedQuery()->update(['ballot_id' => $ballot->id]);
        }

        ActivityLog::record('voters.assigned', "{$count} électeur(s) assigné(s) à l'urne « {$ballot->title} »", $this->election);
        $this->selectedForAssign = [];
        session()->flash('status', "{$count} électeur(s) assigné(s) à l'urne « {$ballot->title} ».");
    }

    public function unassignVoter(ElectionVoter $voter): void
    {
        abort_unless($voter->election_id === $this->election->id, 403);
        $voter->update(['ballot_id' => null]);
    }

    public function toggleSent(ElectionVoter $voter): void
    {
        abort_unless($voter->election_id === $this->election->id, 403);
        $voter->update(['email_sent_at' => $voter->email_sent_at ? null : now()]);
    }

    public function addSource(): void
    {
        $this->validate(['selectedFormId' => ['required', 'exists:forms,id']]);

        $form = CensusForm::findOrFail($this->selectedFormId);
        $added = app(ElectionVoterService::class)->addFromForm($this->election, $form, $this->nameFilter ?: null);

        session()->flash('status', "{$added} électeur(s) ajouté(s).");
        $this->reset(['selectedFormId', 'nameFilter']);
    }

    public function removeVoter(ElectionVoter $voter): void
    {
        abort_unless($voter->election_id === $this->election->id, 403);
        $voter->delete();
    }

    public function revoke(ElectionVoter $voter): void
    {
        abort_unless($voter->election_id === $this->election->id, 403);
        abort_if($voter->hasVoted(), 403);
        $voter->update(['token' => (string) \Illuminate\Support\Str::uuid(), 'email_sent_at' => null]);
        ActivityLog::record('invitation.revoked', "Lien de vote régénéré pour {$voter->name}");
    }

    public function sendEmails(): void
    {
        $voters = $this->election->voters()->whereNull('email_sent_at')->get();
        foreach ($voters as $voter) {
            SendVoteLinkEmail::dispatch($voter);
        }
        ActivityLog::record('invitations.sent', "Envoi de {$voters->count()} invitation(s) pour « {$this->election->title} »");
        session()->flash('status', "Envoi de {$voters->count()} email(s) en cours.");
    }

    public function resend(ElectionVoter $voter): void
    {
        abort_unless($voter->election_id === $this->election->id, 403);
        SendVoteLinkEmail::dispatch($voter);
        session()->flash('status', "Invitation relancée pour {$voter->name}.");
    }

    public function exportExcel()
    {
        $voters = $this->election->voters()->with('ballot')->get();

        return ExcelExport::download(
            $voters->map(fn ($voter) => [
                'Nom' => $voter->name,
                'Email' => $voter->email,
                'Téléphone' => $voter->phone,
                'Urne assignée' => $voter->ballot?->title ?? '—',
                'Lien de vote unique' => $voter->voteUrl(),
                'Lien envoyé' => $voter->email_sent_at ? 'Oui' : 'Non',
                'A voté' => $voter->hasVoted() ? 'Oui' : 'Non',
            ])->all(),
            'invitations-election-'.$this->election->id
        );
    }

    public function exportCsv()
    {
        $voters = $this->election->voters()->get();

        return Response::streamDownload(function () use ($voters) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Nom', 'Email', 'Téléphone', 'Lien de vote unique', 'A voté']);
            foreach ($voters as $voter) {
                fputcsv($out, [$voter->name, $voter->email, $voter->phone, $voter->voteUrl(), $voter->hasVoted() ? 'Oui' : 'Non']);
            }
            fclose($out);
        }, 'invitations-election-'.$this->election->id.'.csv');
    }

    public function with(): array
    {
        $voters = $this->election->voters()->with('ballot')->orderBy('name');
        if ($this->search !== '') {
            $voters->where(fn ($q) => $q->where('name', 'like', '%'.$this->search.'%')->orWhere('email', 'like', '%'.$this->search.'%'));
        }
        $all = $voters->get();

        return [
            'forms' => CensusForm::where('status', '!=', 'draft')->get(),
            'voters' => $all,
            'sources' => $this->election->sources()->with('form')->get(),
            'ballots' => $this->election->ballots,
            'unassignedCount' => $this->unassignedQuery()->count(),
            'criterionFields' => FormField::where('is_identity_name', false)->get()->filter->hasOptions()->unique('label')->sortBy('label'),
            'stats' => [
                'total' => $all->count(),
                'sent' => $all->whereNotNull('email_sent_at')->count(),
                'notSent' => $all->whereNull('email_sent_at')->count(),
                'voted' => $all->whereNotNull('used_at')->count(),
            ],
        ];
    }
}; ?>

<div>
    <x-page-header :title="$election->title" description="Liens de vote uniques et invitations.">
        <x-slot:actions>
            <a href="{{ route('admin.votes.show', $election) }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"><x-icon name="arrow-left" class="size-4" /> Suivi</a>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-success-soft text-success text-sm px-4 py-2">{{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-6">
        <x-stat-card label="Total invitations" :value="$stats['total']" icon="mail" tone="muted" />
        <x-stat-card label="Envoyées" :value="$stats['sent']" icon="check" tone="primary" />
        <x-stat-card label="Non envoyées" :value="$stats['notSent']" icon="clipboard" tone="gold" />
        <x-stat-card label="Votes effectués" :value="$stats['voted']" icon="activity" tone="primary" />
    </div>

    @if ($ballots->count() > 1)
        <div class="rounded-xl border border-border bg-card p-5 shadow-card mb-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold">Assignation aux urnes</h2>
                <span class="text-xs text-muted-foreground">{{ $unassignedCount }} électeur(s) non assigné(s)</span>
            </div>
            <div class="grid grid-cols-2 gap-3 mb-4 sm:grid-cols-4">
                @foreach ($ballots as $ballot)
                    <div class="rounded-lg border border-border p-3 text-sm">
                        <p class="font-semibold">{{ $ballot->title }}</p>
                        <p class="text-xs text-muted-foreground">{{ $voters->where('ballot_id', $ballot->id)->count() }} électeur(s)</p>
                    </div>
                @endforeach
            </div>
            <form wire:submit="assignToBallot" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Critère (établissement, niveau, ville...)</label>
                    <select wire:model.live="criterionFieldId" class="mt-1 block w-full rounded-lg border-border text-sm">
                        <option value="">Tous les non assignés</option>
                        @foreach ($criterionFields as $cf)
                            <option value="{{ $cf->id }}">{{ $cf->label }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($criterionFieldId)
                    @php $cf = $criterionFields->firstWhere('id', $criterionFieldId); @endphp
                    <div>
                        <label class="text-xs font-medium text-muted-foreground">Valeur</label>
                        <select wire:model.live="criterionValue" class="mt-1 block w-full rounded-lg border-border text-sm">
                            <option value="">—</option>
                            @foreach ($cf?->effectiveOptions() ?? [] as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Assigner à l'urne</label>
                    <select wire:model="assignBallotId" class="mt-1 block w-full rounded-lg border-border text-sm">
                        <option value="">— Choisir —</option>
                        @foreach ($ballots as $ballot)
                            <option value="{{ $ballot->id }}">{{ $ballot->title }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-deep">Assigner le groupe</button>
            </form>
            <p class="mt-2 text-xs text-muted-foreground">Sans critère, l'action assigne tous les électeurs non assignés à l'urne choisie. Un électeur assigné ne peut voter que dans son urne.</p>
        </div>
    @endif

    <div class="rounded-xl border border-border bg-card p-5 shadow-card mb-6">
        <h2 class="text-sm font-semibold mb-3">Ajouter des électeurs depuis un formulaire</h2>
        <form wire:submit="addSource" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
            <select wire:model="selectedFormId" class="rounded-lg border-border text-sm">
                <option value="">— Choisir —</option>
                @foreach ($forms as $form)
                    <option value="{{ $form->id }}">{{ $form->title }}</option>
                @endforeach
            </select>
            <input type="text" wire:model="nameFilter" placeholder="Filtrer par nom (optionnel)" class="rounded-lg border-border text-sm">
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-deep">Ajouter les inscriptions validées</button>
        </form>
        @if ($sources->isNotEmpty())
            <div class="mt-3 text-xs text-muted-foreground">
                Sources : @foreach ($sources as $s) <span class="inline-flex bg-muted px-2 py-0.5 rounded mr-1">{{ $s->form->title }}</span> @endforeach
            </div>
        @endif
    </div>

    <div class="rounded-xl border border-border bg-card p-5 shadow-card">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <h2 class="text-sm font-semibold">Liste électorale ({{ $voters->count() }})</h2>
            <div class="flex items-center gap-2">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Rechercher..." class="rounded-lg border-border text-sm">
                <button type="button" wire:click="sendEmails" wire:confirm="Envoyer le lien de vote par email à tous les électeurs qui ne l'ont pas encore reçu ?" class="rounded-lg border border-border px-3 py-1.5 text-sm font-semibold hover:bg-muted">Envoyer les liens</button>
                <button type="button" wire:click="exportExcel" class="rounded-lg bg-primary px-3 py-1.5 text-sm font-semibold text-white hover:bg-primary-deep">Exporter Excel</button>
                <button type="button" wire:click="exportCsv" class="rounded-lg border border-border px-3 py-1.5 text-sm font-semibold hover:bg-muted">CSV</button>
            </div>
        </div>

        <div class="divide-y divide-border">
            @forelse ($voters as $voter)
                <div class="py-3 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <x-person-avatar :name="$voter->name" class="size-7" />
                        <div>
                            <p class="font-medium text-sm">{{ $voter->name }}</p>
                            <p class="text-xs text-muted-foreground">{{ $voter->email }}</p>
                            @if ($voter->ballot)
                                <p class="text-xs text-primary">Urne : {{ $voter->ballot->title }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        @if ($voter->hasVoted())
                            <x-status-badge tone="success">A voté</x-status-badge>
                        @elseif ($voter->email_sent_at)
                            <x-status-badge tone="gold">Envoyée</x-status-badge>
                        @else
                            <x-status-badge tone="muted">Non envoyée</x-status-badge>
                        @endif
                        <label class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                            <input type="checkbox" {{ $voter->email_sent_at ? 'checked' : '' }} wire:click="toggleSent({{ $voter->id }})" class="rounded border-border"> Lien envoyé
                        </label>
                        <button type="button" x-data="{ copied: false }" @click="navigator.clipboard.writeText('{{ $voter->voteUrl() }}'); copied = true; setTimeout(() => copied = false, 1600)" class="text-xs font-medium text-primary hover:underline">
                            <span x-text="copied ? 'Copié' : 'Copier le lien'"></span>
                        </button>
                        @if ($voter->ballot)
                            <button type="button" wire:click="unassignVoter({{ $voter->id }})" class="text-xs font-medium text-muted-foreground hover:underline">Désassigner</button>
                        @endif
                        @unless ($voter->hasVoted())
                            <button type="button" wire:click="resend({{ $voter->id }})" class="text-xs font-medium text-primary hover:underline">Relancer</button>
                            <button type="button" wire:click="revoke({{ $voter->id }})" wire:confirm="Révoquer et régénérer le lien ?" class="text-xs font-medium text-gold-foreground hover:underline">Révoquer</button>
                        @endunless
                        <button type="button" wire:click="removeVoter({{ $voter->id }})" wire:confirm="Retirer cet électeur ?" class="text-xs font-medium text-destructive hover:underline">Retirer</button>
                    </div>
                </div>
            @empty
                <x-empty-state icon="mail" title="Aucun électeur" />
            @endforelse
        </div>
    </div>
</div>
