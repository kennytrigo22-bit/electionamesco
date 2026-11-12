<?php

use App\Models\ActivityLog;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Support\ExcelExport;
use Illuminate\Support\Facades\Response;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin', ['title' => 'Membres'])] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public ?int $criterionFieldId = null;

    public string $criterionValue = '';

    public array $selected = [];

    public bool $showFilters = false;

    public function mount(): void
    {
        $this->search = (string) request()->query('q', '');
    }

    public function updatedCriterionFieldId(): void
    {
        $this->criterionValue = '';
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'criterionFieldId', 'criterionValue']);
    }

    protected function setStatus(FormSubmission $submission, string $status): void
    {
        $submission->update(['status' => $status, 'validated_by' => auth()->id(), 'validated_at' => now()]);
        ActivityLog::record('member.'.$status, "Membre « {$submission->displayName()} » : ".$submission->statusLabel(), $submission);
    }

    public function validateMember(FormSubmission $submission): void
    {
        $this->setStatus($submission, 'validated');
    }

    public function rejectMember(FormSubmission $submission): void
    {
        $this->setStatus($submission, 'rejected');
    }

    public function suspendMember(FormSubmission $submission): void
    {
        $this->setStatus($submission, 'suspended');
    }

    public function bulkAction(string $status): void
    {
        foreach (FormSubmission::whereIn('id', $this->selected)->get() as $submission) {
            $this->setStatus($submission, $status);
        }
        $this->selected = [];
    }

    protected function filteredQuery()
    {
        $query = FormSubmission::with(['values.field', 'form'])->latest('submitted_at');

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->criterionFieldId && $this->criterionValue !== '') {
            $query->whereHas('values', fn ($q) => $q->where('form_field_id', $this->criterionFieldId)->where('value', $this->criterionValue));
        }

        return $query;
    }

    public function exportExcel()
    {
        $members = $this->filteredQuery()->get();

        return ExcelExport::download(
            $members->map(fn ($m) => [
                'Nom' => $m->displayName(),
                'Formulaire' => $m->form->title,
                'Statut' => $m->statusLabel(),
                'Éligibilité' => $m->eligibilityLabel(),
                "Date d'inscription" => $m->submitted_at->format('d/m/Y'),
            ])->all(),
            'membres-amesco'
        );
    }

    public function exportCsv()
    {
        $members = $this->filteredQuery()->get();

        return Response::streamDownload(function () use ($members) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Nom', 'Formulaire', 'Statut', 'Éligibilité', 'Date inscription']);
            foreach ($members as $m) {
                fputcsv($out, [$m->displayName(), $m->form->title, $m->statusLabel(), $m->eligibilityLabel(), $m->submitted_at->format('d/m/Y')]);
            }
            fclose($out);
        }, 'membres.csv');
    }

    public function with(): array
    {
        $members = $this->filteredQuery()->paginate(25);

        if ($this->search !== '') {
            $members->setCollection(
                $members->getCollection()->filter(fn ($m) => str_contains(mb_strtolower($m->displayName()), mb_strtolower($this->search)))
            );
        }

        $activeFilters = ($this->criterionFieldId ? 1 : 0);

        return [
            'members' => $members,
            'criterionFields' => FormField::where('is_identity_name', false)->get()->filter->hasOptions()->unique('label')->sortBy('label'),
            'activeFilters' => $activeFilters,
            'stats' => [
                'total' => FormSubmission::count(),
                'validated' => FormSubmission::where('status', 'validated')->count(),
                'pending' => FormSubmission::where('status', 'pending')->count(),
                'rejected' => FormSubmission::where('status', 'rejected')->count(),
            ],
        ];
    }
}; ?>

<div>
    <x-page-header title="Membres" description="Annuaire de toutes les inscriptions, tous formulaires confondus.">
        <x-slot:actions>
            <button type="button" wire:click="exportExcel" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-deep"><x-icon name="download" class="size-4" /> Exporter Excel</button>
            <button type="button" wire:click="exportCsv" class="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-muted"><x-icon name="file-text" class="size-4" /> CSV</button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-6">
        <x-stat-card label="Total" :value="$stats['total']" icon="users" tone="muted" />
        <x-stat-card label="Validés" :value="$stats['validated']" icon="user-check" tone="primary" />
        <x-stat-card label="En attente" :value="$stats['pending']" icon="clipboard" tone="gold" />
        <x-stat-card label="Rejetés" :value="$stats['rejected']" icon="x" tone="destructive" />
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Rechercher un membre..." class="rounded-lg border-border text-sm max-w-xs">
        <select wire:model.live="statusFilter" class="rounded-lg border-border text-sm">
            <option value="all">Tous les statuts</option>
            <option value="pending">En attente</option>
            <option value="validated">Validés</option>
            <option value="rejected">Rejetés</option>
            <option value="suspended">Suspendus</option>
        </select>
        <button type="button" wire:click="$toggle('showFilters')" class="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-1.5 text-sm font-semibold hover:bg-muted {{ $activeFilters ? 'bg-primary-soft text-primary-deep' : '' }}">
            <x-icon name="settings" class="size-4" /> Filtres @if($activeFilters)<span class="rounded bg-primary px-1.5 text-[10px] text-white">{{ $activeFilters }}</span>@endif
        </button>
        @if ($selected)
            <span class="ml-auto flex items-center gap-2 rounded-lg bg-primary-soft/60 px-3 py-1.5 text-sm">
                <span class="font-semibold text-primary-deep">{{ count($selected) }} sélectionné(s)</span>
                <button type="button" wire:click="bulkAction('validated')" class="font-semibold text-primary hover:underline">Valider</button>
                <button type="button" wire:click="bulkAction('rejected')" class="font-semibold text-destructive hover:underline">Rejeter</button>
            </span>
        @endif
    </div>

    @if ($showFilters)
        <div class="mb-4 grid gap-3 rounded-xl border border-border bg-surface p-4 sm:grid-cols-3">
            <div>
                <label class="text-xs font-medium text-muted-foreground">Autre critère (établissement, niveau, région, quartier...)</label>
                <select wire:model.live="criterionFieldId" class="mt-1 block w-full rounded-lg border-border text-sm">
                    <option value="">—</option>
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
            <div class="flex items-end">
                <button type="button" wire:click="resetFilters" class="text-sm font-medium text-muted-foreground hover:text-foreground">Réinitialiser les filtres</button>
            </div>
        </div>
    @endif

    <div class="rounded-xl border border-border bg-card shadow-card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                <tr>
                    <th class="w-10 px-4 py-3"></th>
                    <th class="px-4 py-3 text-left">Membre</th>
                    <th class="px-4 py-3 text-left">Formulaire</th>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Statut</th>
                    <th class="px-4 py-3 text-left">Éligibilité</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($members as $member)
                    <tr>
                        <td class="px-4 py-3"><input type="checkbox" wire:model.live="selected" value="{{ $member->id }}" class="rounded border-border"></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <x-person-avatar :name="$member->displayName()" class="size-7" />
                                <span class="font-medium">{{ $member->displayName() }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">{{ $member->form->title }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ $member->submitted_at->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">
                            <x-status-badge :tone="match($member->status) { 'validated' => 'success', 'pending' => 'gold', 'rejected' => 'destructive', 'suspended' => 'muted' }">{{ $member->statusLabel() }}</x-status-badge>
                        </td>
                        <td class="px-4 py-3">
                            @if ($member->eligibility)
                                <x-status-badge :tone="match($member->eligibility) { 'eligible' => 'success', 'non_eligible' => 'destructive', 'verification' => 'gold' }">{{ $member->eligibilityLabel() }}</x-status-badge>
                            @else
                                <span class="text-xs text-muted-foreground">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-1.5">
                                @if ($member->status !== 'validated')
                                    <button type="button" wire:click="validateMember({{ $member->id }})" class="rounded-lg p-1.5 text-primary hover:bg-primary-soft" title="Valider"><x-icon name="check" class="size-4" /></button>
                                @endif
                                @if ($member->status !== 'rejected')
                                    <button type="button" wire:click="rejectMember({{ $member->id }})" class="rounded-lg p-1.5 text-destructive hover:bg-destructive-soft" title="Rejeter"><x-icon name="x" class="size-4" /></button>
                                @endif
                                @if ($member->status !== 'suspended')
                                    <button type="button" wire:click="suspendMember({{ $member->id }})" wire:confirm="Suspendre ce membre ?" class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted" title="Suspendre"><x-icon name="lock" class="size-4" /></button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty-state icon="users" title="Aucun membre" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $members->links() }}</div>
</div>
