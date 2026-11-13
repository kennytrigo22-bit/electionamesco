<?php

use App\Models\ActivityLog;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\Setting;
use App\Support\ExcelExport;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin', ['title' => 'Fichier électoral'])] class extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $criterionFieldId = null;

    public string $criterionValue = '';

    public bool $showFilters = false;

    public array $selected = [];

    public ?FormSubmission $detail = null;

    public function toggleSelectAll(array $pageIds): void
    {
        $this->selected = count(array_intersect($pageIds, $this->selected)) === count($pageIds)
            ? array_values(array_diff($this->selected, $pageIds))
            : array_values(array_unique([...$this->selected, ...$pageIds]));
    }

    public function updatedCriterionFieldId(): void
    {
        $this->criterionValue = '';
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'criterionFieldId', 'criterionValue']);
    }

    public function showDetail(FormSubmission $submission): void
    {
        $this->detail = $submission->load(['values.field', 'form']);
    }

    public function closeDetail(): void
    {
        $this->detail = null;
    }

    public function setEligibility(FormSubmission $submission, string $eligibility): void
    {
        abort_unless($submission->status === 'validated', 403);

        if (Setting::get('electoral_register_locked_at')) {
            session()->flash('error', 'Le fichier électoral est verrouillé. Déverrouillez-le avant de modifier une éligibilité.');

            return;
        }

        $submission->update(['eligibility' => $eligibility]);
        ActivityLog::record('eligibility.updated', "Éligibilité de « {$submission->displayName()} » : ".$submission->eligibilityLabel(), $submission);
    }

    public function bulkSetEligibility(string $eligibility): void
    {
        if (Setting::get('electoral_register_locked_at')) {
            session()->flash('error', 'Le fichier électoral est verrouillé. Déverrouillez-le avant de modifier une éligibilité.');

            return;
        }

        foreach (FormSubmission::whereIn('id', $this->selected)->where('status', 'validated')->get() as $submission) {
            $submission->update(['eligibility' => $eligibility]);
            ActivityLog::record('eligibility.updated', "Éligibilité de « {$submission->displayName()} » : ".$submission->eligibilityLabel(), $submission);
        }

        $this->selected = [];
    }

    public function bulkSetEligibilityForFiltered(string $eligibility): void
    {
        if (Setting::get('electoral_register_locked_at')) {
            session()->flash('error', 'Le fichier électoral est verrouillé. Déverrouillez-le avant de modifier une éligibilité.');

            return;
        }

        $count = 0;
        foreach ($this->filteredQuery()->get() as $submission) {
            $submission->update(['eligibility' => $eligibility]);
            ActivityLog::record('eligibility.updated', "Éligibilité de « {$submission->displayName()} » : ".$submission->eligibilityLabel(), $submission);
            $count++;
        }

        session()->flash('status', "{$count} électeur(s) filtré(s) mis à jour.");
    }

    public function lockRegister(): void
    {
        Setting::set('electoral_register_locked_at', now()->toDateTimeString());
        ActivityLog::record('register.locked', 'Fichier électoral verrouillé');
    }

    public function unlockRegister(): void
    {
        Setting::set('electoral_register_locked_at', null);
        ActivityLog::record('register.unlocked', 'Fichier électoral déverrouillé');
    }

    public function exportExcel()
    {
        $voters = $this->filteredQuery()->get();

        return ExcelExport::download(
            $voters->map(fn ($v) => [
                'Nom' => $v->displayName(),
                'Formulaire source' => $v->form->title,
                'Éligibilité' => $v->eligibilityLabel(),
            ])->all(),
            'fichier-electoral-amesco'
        );
    }

    protected function filteredQuery()
    {
        $query = FormSubmission::where('status', 'validated')->with(['values.field', 'form'])->latest('submitted_at');

        if ($this->search !== '') {
            $query->whereHas('values', fn ($q) => $q->where('value', 'like', '%'.$this->search.'%'));
        }

        if ($this->criterionFieldId && $this->criterionValue !== '') {
            $query->whereHas('values', fn ($q) => $q->where('form_field_id', $this->criterionFieldId)->where('value', $this->criterionValue));
        }

        return $query;
    }

    public function with(): array
    {
        $base = FormSubmission::where('status', 'validated')->with(['values.field', 'form']);

        $voters = $this->filteredQuery()->paginate(25);

        $duplicates = (clone $base)->get()->countBy(fn ($m) => $m->displayName())->filter(fn ($count) => $count > 1)->count();

        return [
            'voters' => $voters,
            'criterionFields' => FormField::where('is_identity_name', false)->get()->filter->hasOptions()->unique('label')->sortBy('label'),
            'activeFilters' => $this->criterionFieldId ? 1 : 0,
            'lockedAt' => Setting::get('electoral_register_locked_at'),
            'stats' => [
                'total' => (clone $base)->count(),
                'eligible' => (clone $base)->where('eligibility', 'eligible')->count(),
                'nonEligible' => (clone $base)->where('eligibility', 'non_eligible')->count(),
                'verification' => (clone $base)->where(fn ($q) => $q->whereNull('eligibility')->orWhere('eligibility', 'verification'))->count(),
                'duplicates' => $duplicates,
            ],
        ];
    }
}; ?>

<div>
    <x-page-header title="Fichier électoral" description="Registre consolidé des membres validés, base de toutes les listes électorales.">
        <x-slot:actions>
            <button type="button" wire:click="exportExcel" class="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-muted"><x-icon name="download" class="size-4" /> Exporter Excel</button>
            @if ($lockedAt)
                <button type="button" wire:click="unlockRegister" class="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-muted"><x-icon name="lock" class="size-4" /> Déverrouiller</button>
            @else
                <button type="button" wire:click="lockRegister" wire:confirm="Verrouiller le fichier électoral avant une élection ?" wire:loading.attr="disabled" wire:target="lockRegister" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-deep disabled:opacity-50"><x-icon name="lock" class="size-4" /> Verrouiller</button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($lockedAt)
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-gold-soft text-gold-foreground text-sm px-4 py-2">
            <x-icon name="lock" class="size-4" /> Fichier électoral verrouillé depuis le {{ \Illuminate\Support\Carbon::parse($lockedAt)->format('d/m/Y H:i') }}
        </div>
    @endif

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-success-soft text-success text-sm px-4 py-2">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-destructive-soft text-destructive text-sm px-4 py-2">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-5 mb-6">
        <x-stat-card label="Membres validés" :value="$stats['total']" icon="users" tone="muted" />
        <x-stat-card label="Électeurs éligibles" :value="$stats['eligible']" icon="book-user" tone="primary" />
        <x-stat-card label="Non éligibles" :value="$stats['nonEligible']" icon="x" tone="destructive" />
        <x-stat-card label="À vérifier" :value="$stats['verification']" icon="eye" tone="gold" />
        <x-stat-card label="Doublons potentiels" :value="$stats['duplicates']" icon="sparkles" tone="destructive" />
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Rechercher..." class="rounded-lg border-border text-sm max-w-xs">
        <button type="button" wire:click="$toggle('showFilters')" class="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-1.5 text-sm font-semibold hover:bg-muted {{ $activeFilters ? 'bg-primary-soft text-primary-deep' : '' }}">
            <x-icon name="settings" class="size-4" /> Filtres @if($activeFilters)<span class="rounded bg-primary px-1.5 text-[10px] text-white">{{ $activeFilters }}</span>@endif
        </button>
    </div>

    @if ($showFilters)
        <div class="mb-4 grid gap-3 rounded-xl border border-border bg-surface p-4 sm:grid-cols-3">
            <div>
                <label class="text-xs font-medium text-muted-foreground">Critère (établissement, niveau, région, quartier...)</label>
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
        @if ($criterionFieldId && $criterionValue !== '')
            <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg bg-gold-soft px-4 py-2.5 text-sm">
                <span class="font-semibold text-gold-foreground">Appliquer à tous les résultats filtrés ({{ $voters->total() }}) :</span>
                <button type="button" wire:click="bulkSetEligibilityForFiltered('eligible')" wire:confirm="Rendre éligibles tous les électeurs correspondant à ce filtre ?" class="font-semibold text-primary hover:underline">Rendre éligible</button>
                <button type="button" wire:click="bulkSetEligibilityForFiltered('non_eligible')" wire:confirm="Exclure tous les électeurs correspondant à ce filtre ?" class="font-semibold text-destructive hover:underline">Rendre non éligible</button>
                <button type="button" wire:click="bulkSetEligibilityForFiltered('verification')" wire:confirm="Marquer « à vérifier » tous les électeurs correspondant à ce filtre ?" class="font-semibold text-gold-foreground hover:underline">À vérifier</button>
            </div>
        @endif
    @endif

    @if ($selected)
        <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg bg-primary-soft/60 px-4 py-2.5 text-sm">
            <span class="font-semibold text-primary-deep">{{ count($selected) }} sélectionné(s)</span>
            <button type="button" wire:click="bulkSetEligibility('eligible')" class="font-semibold text-primary hover:underline">Valider l'éligibilité</button>
            <button type="button" wire:click="bulkSetEligibility('non_eligible')" class="font-semibold text-destructive hover:underline">Exclure</button>
            <button type="button" wire:click="bulkSetEligibility('verification')" class="font-semibold text-gold-foreground hover:underline">À vérifier</button>
            <button type="button" wire:click="$set('selected', [])" class="text-muted-foreground hover:underline">Annuler la sélection</button>
        </div>
    @endif

    <div class="rounded-xl border border-border bg-card shadow-card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                <tr>
                    <th class="w-10 px-4 py-3"><input type="checkbox" wire:click="toggleSelectAll({{ json_encode($voters->pluck('id')->all()) }})" @checked($voters->isNotEmpty() && $voters->pluck('id')->diff($selected)->isEmpty()) class="rounded border-border"></th>
                    <th class="px-4 py-3 text-left">Électeur</th>
                    <th class="px-4 py-3 text-left">Formulaire source</th>
                    <th class="px-4 py-3 text-left">Éligibilité</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($voters as $voter)
                    <tr class="hover:bg-surface/70">
                        <td class="px-4 py-3" onclick="event.stopPropagation()"><input type="checkbox" wire:model.live="selected" value="{{ $voter->id }}" class="rounded border-border"></td>
                        <td class="px-4 py-3 cursor-pointer" wire:click="showDetail({{ $voter->id }})">
                            <div class="flex items-center gap-2">
                                <x-person-avatar :name="$voter->displayName()" class="size-7" />
                                <span class="font-medium hover:text-primary">{{ $voter->displayName() }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground cursor-pointer" wire:click="showDetail({{ $voter->id }})">{{ $voter->form->title }}</td>
                        <td class="px-4 py-3">
                            <x-status-badge :tone="match($voter->eligibility) { 'eligible' => 'success', 'non_eligible' => 'destructive', default => 'gold' }">{{ $voter->eligibilityLabel() }}</x-status-badge>
                        </td>
                        <td class="px-4 py-3 text-right" onclick="event.stopPropagation()">
                            <div class="inline-flex gap-1.5">
                                <button type="button" wire:click="setEligibility({{ $voter->id }}, 'eligible')" class="rounded-lg px-2 py-1 text-xs font-semibold text-primary hover:bg-primary-soft">Éligible</button>
                                <button type="button" wire:click="setEligibility({{ $voter->id }}, 'non_eligible')" class="rounded-lg px-2 py-1 text-xs font-semibold text-destructive hover:bg-destructive-soft">Non éligible</button>
                                <button type="button" wire:click="setEligibility({{ $voter->id }}, 'verification')" class="rounded-lg px-2 py-1 text-xs font-semibold text-gold-foreground hover:bg-gold-soft">À vérifier</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty-state icon="book-user" title="Aucun électeur validé" description="Validez des inscriptions dans Membres pour alimenter le fichier électoral." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $voters->links() }}</div>

    @if ($detail)
        <div class="fixed inset-0 z-40 flex items-start justify-end bg-black/30 p-4" wire:click.self="closeDetail">
            <div class="h-full w-full max-w-md overflow-y-auto rounded-xl bg-card p-6 shadow-elevated">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold">{{ $detail->displayName() }}</h2>
                    <button type="button" wire:click="closeDetail" class="rounded-lg p-1.5 hover:bg-muted"><x-icon name="x" class="size-4" /></button>
                </div>
                <div class="mb-4 flex gap-2">
                    <x-status-badge :tone="match($detail->eligibility) { 'eligible' => 'success', 'non_eligible' => 'destructive', default => 'gold' }">{{ $detail->eligibilityLabel() }}</x-status-badge>
                    <x-status-badge tone="muted">{{ $detail->form->title }}</x-status-badge>
                </div>
                <dl class="space-y-3 text-sm">
                    @foreach ($detail->form->fields as $field)
                        @php $value = $detail->valueFor($field); @endphp
                        <div>
                            <dt class="text-xs text-muted-foreground">{{ $field->label }}</dt>
                            <dd class="font-medium">
                                @if ($value?->isImage())
                                    <img src="{{ $value->fileUrl() }}" class="mt-1 h-20 w-20 rounded-lg border border-border object-cover">
                                @elseif ($value?->file_path)
                                    <a href="{{ $value->fileUrl() }}" target="_blank" class="text-primary hover:underline">{{ $value->value }}</a>
                                @else
                                    {{ $value?->value ?: '—' }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>
    @endif
</div>
