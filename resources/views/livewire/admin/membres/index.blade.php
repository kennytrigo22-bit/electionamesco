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

    public ?FormSubmission $detail = null;

    public bool $editing = false;

    public array $editValues = [];

    public function mount(): void
    {
        $this->search = (string) request()->query('q', '');
    }

    public function showDetail(FormSubmission $submission): void
    {
        $this->detail = $submission->load(['values.field', 'form.fields']);
        $this->editing = false;
    }

    public function closeDetail(): void
    {
        $this->detail = null;
        $this->editing = false;
    }

    public function startEdit(): void
    {
        $this->editing = true;
        $this->editValues = $this->detail->values->mapWithKeys(fn ($v) => [$v->form_field_id => $v->value])->all();
    }

    public function saveEdit(): void
    {
        $before = $this->detail->values->mapWithKeys(fn ($v) => [$v->field->label => $v->value])->all();
        $changes = [];

        foreach ($this->detail->form->fields as $field) {
            if ($field->isFileType()) {
                continue;
            }

            $newValue = trim((string) ($this->editValues[$field->id] ?? ''));
            $old = $before[$field->label] ?? null;

            if ($newValue !== (string) $old) {
                $changes[] = "{$field->label} : « ".($old ?: '—')." » → « {$newValue} »";
            }

            $value = $this->detail->valueFor($field);
            if ($value) {
                $value->update(['value' => $newValue]);
            } else {
                $this->detail->values()->create(['form_field_id' => $field->id, 'value' => $newValue]);
            }
        }

        if ($changes) {
            ActivityLog::record('member.updated', "Fiche de « {$this->detail->displayName()} » modifiée : ".implode(' ; ', $changes), $this->detail);
        }

        $this->detail = $this->detail->fresh(['values.field', 'form.fields']);
        $this->editing = false;
    }

    public function deleteMember(FormSubmission $submission): void
    {
        $submission->load('values.field', 'form');
        $name = $submission->displayName();
        $formTitle = $submission->form->title;
        $summary = $submission->values
            ->filter(fn ($v) => ! $v->field->isFileType())
            ->map(fn ($v) => "{$v->field->label} : {$v->value}")
            ->implode(' ; ');

        $submission->values()->delete();
        $submission->delete();

        ActivityLog::record('member.deleted', "Membre « {$name} » supprimé (formulaire « {$formTitle} ») — {$summary}");

        if ($this->detail?->id === $submission->id) {
            $this->detail = null;
        }
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
                    <tr class="hover:bg-surface/70">
                        <td class="px-4 py-3" onclick="event.stopPropagation()"><input type="checkbox" wire:model.live="selected" value="{{ $member->id }}" class="rounded border-border"></td>
                        <td class="px-4 py-3 cursor-pointer" wire:click="showDetail({{ $member->id }})">
                            <div class="flex items-center gap-2">
                                <x-person-avatar :name="$member->displayName()" class="size-7" />
                                <span class="font-medium hover:text-primary">{{ $member->displayName() }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground cursor-pointer" wire:click="showDetail({{ $member->id }})">{{ $member->form->title }}</td>
                        <td class="px-4 py-3 text-muted-foreground cursor-pointer" wire:click="showDetail({{ $member->id }})">{{ $member->submitted_at->format('d/m/Y') }}</td>
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
                        <td class="px-4 py-3 text-right" onclick="event.stopPropagation()">
                            <div class="flex justify-end gap-1.5">
                                @if ($member->status !== 'validated')
                                    <button type="button" wire:click="validateMember({{ $member->id }})" wire:loading.attr="disabled" class="rounded-lg p-1.5 text-primary hover:bg-primary-soft disabled:opacity-40" title="Valider"><x-icon name="check" class="size-4" /></button>
                                @endif
                                @if ($member->status !== 'rejected')
                                    <button type="button" wire:click="rejectMember({{ $member->id }})" wire:loading.attr="disabled" class="rounded-lg p-1.5 text-destructive hover:bg-destructive-soft disabled:opacity-40" title="Rejeter"><x-icon name="x" class="size-4" /></button>
                                @endif
                                @if ($member->status !== 'suspended')
                                    <button type="button" wire:click="suspendMember({{ $member->id }})" wire:confirm="Suspendre ce membre ?" wire:loading.attr="disabled" class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted disabled:opacity-40" title="Suspendre"><x-icon name="lock" class="size-4" /></button>
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

    @if ($detail)
        <div class="fixed inset-0 z-40 flex items-start justify-end bg-black/30 p-4" wire:click.self="closeDetail">
            <div class="h-full w-full max-w-lg overflow-y-auto rounded-xl bg-card p-6 shadow-elevated">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold">{{ $detail->displayName() }}</h2>
                    <button type="button" wire:click="closeDetail" class="rounded-lg p-1.5 hover:bg-muted"><x-icon name="x" class="size-4" /></button>
                </div>
                <div class="mb-4 flex flex-wrap gap-2">
                    <x-status-badge :tone="match($detail->status) { 'validated' => 'success', 'pending' => 'gold', 'rejected' => 'destructive', 'suspended' => 'muted' }">{{ $detail->statusLabel() }}</x-status-badge>
                    @if ($detail->eligibility)
                        <x-status-badge :tone="match($detail->eligibility) { 'eligible' => 'success', 'non_eligible' => 'destructive', 'verification' => 'gold' }">{{ $detail->eligibilityLabel() }}</x-status-badge>
                    @endif
                    <x-status-badge tone="muted">{{ $detail->form->title }}</x-status-badge>
                </div>

                @if ($editing)
                    <form wire:submit="saveEdit" class="space-y-3">
                        @foreach ($detail->form->fields as $field)
                            @continue($field->isFileType())
                            <div>
                                <label class="text-xs font-medium text-muted-foreground">{{ $field->label }}</label>
                                @if ($field->hasOptions())
                                    <select wire:model="editValues.{{ $field->id }}" class="mt-1 block w-full rounded-lg border-border text-sm">
                                        <option value="">—</option>
                                        @foreach ($field->effectiveOptions() as $option)
                                            <option value="{{ $option }}">{{ $option }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="text" wire:model="editValues.{{ $field->id }}" class="mt-1 block w-full rounded-lg border-border text-sm">
                                @endif
                            </div>
                        @endforeach
                        <div class="flex gap-2 pt-2">
                            <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-deep disabled:opacity-60">Enregistrer</button>
                            <button type="button" wire:click="$set('editing', false)" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-muted">Annuler</button>
                        </div>
                    </form>
                @else
                    <dl class="space-y-3 text-sm mb-6">
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
                    <div class="flex flex-wrap gap-2 border-t border-border pt-4">
                        @if ($detail->status !== 'validated')
                            <button type="button" wire:click="validateMember({{ $detail->id }})" class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-deep">Valider</button>
                        @endif
                        @if ($detail->status !== 'rejected')
                            <button type="button" wire:click="rejectMember({{ $detail->id }})" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold hover:bg-muted">Rejeter</button>
                        @endif
                        <button type="button" wire:click="startEdit" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold hover:bg-muted"><x-icon name="pencil" class="size-3.5" /> Modifier</button>
                        <button type="button" wire:click="deleteMember({{ $detail->id }})" wire:confirm="Supprimer définitivement ce membre et toutes ses réponses ? Cette action est irréversible et sera consignée dans les rapports." class="rounded-lg bg-destructive-soft px-3 py-1.5 text-xs font-semibold text-destructive hover:brightness-95"><x-icon name="trash" class="size-3.5" /> Supprimer</button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
