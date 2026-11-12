<?php

use App\Models\ActivityLog;
use App\Models\Form;
use App\Models\FormSubmission;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin', ['title' => 'Inscriptions'])] class extends Component
{
    use WithPagination;

    public Form $form;

    public string $search = '';

    public string $statusFilter = 'all';

    public function mount(Form $form): void
    {
        $this->form = $form->load('fields');
    }

    protected function setStatus(FormSubmission $submission, string $status): void
    {
        abort_unless($submission->form_id === $this->form->id, 403);

        $submission->update([
            'status' => $status,
            'eligibility' => $status === 'validated' ? 'eligible' : ($status === 'rejected' ? 'non_eligible' : $submission->eligibility),
            'validated_by' => auth()->id(),
            'validated_at' => now(),
        ]);

        ActivityLog::record('submission.'.$status, "Inscription de « {$submission->displayName()} » : ".$submission->statusLabel(), $submission);
    }

    public function validateSubmission(FormSubmission $submission): void
    {
        $this->setStatus($submission, 'validated');
    }

    public function rejectSubmission(FormSubmission $submission): void
    {
        $this->setStatus($submission, 'rejected');
    }

    public function with(): array
    {
        $query = $this->form->submissions()->with('values.field')->latest('submitted_at');

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $submissions = $query->paginate(20);

        if ($this->search !== '') {
            $submissions->setCollection(
                $submissions->getCollection()->filter(
                    fn ($submission) => str_contains(mb_strtolower($submission->displayName()), mb_strtolower($this->search))
                )
            );
        }

        return ['submissions' => $submissions];
    }
}; ?>

<div>
    <x-page-header title="Inscriptions" :description="$form->title">
        <x-slot:actions>
            <a href="{{ route('admin.forms.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"><x-icon name="arrow-left" class="size-4" /> Retour</a>
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-wrap items-center gap-3 mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Rechercher par nom..." class="rounded-lg border-border text-sm max-w-xs">
        <select wire:model.live="statusFilter" class="rounded-lg border-border text-sm">
            <option value="all">Tous les statuts</option>
            <option value="pending">En attente</option>
            <option value="validated">Validées</option>
            <option value="rejected">Rejetées</option>
            <option value="suspended">Suspendues</option>
        </select>
    </div>

    <div class="rounded-xl border border-border bg-card shadow-card divide-y divide-border">
        @forelse ($submissions as $submission)
            <div class="p-4 flex flex-wrap items-start justify-between gap-4">
                <div class="flex gap-3 min-w-0">
                    <x-person-avatar :name="$submission->displayName()" />
                    <div class="min-w-0">
                        <p class="font-semibold">{{ $submission->displayName() }}</p>
                        <p class="text-xs text-muted-foreground">Soumis le {{ $submission->submitted_at->format('d/m/Y H:i') }}</p>
                        <div class="mt-2 text-sm text-muted-foreground space-y-1.5">
                            @foreach ($submission->values as $value)
                                <div>
                                    <span class="font-medium text-foreground">{{ $value->field->label }}:</span>
                                    @if ($value->isImage())
                                        <a href="{{ $value->fileUrl() }}" target="_blank"><img src="{{ $value->fileUrl() }}" class="mt-1 h-16 rounded-lg border border-border object-cover"></a>
                                    @elseif ($value->file_path)
                                        <a href="{{ $value->fileUrl() }}" target="_blank" class="text-primary hover:underline">{{ $value->value }}</a>
                                    @else
                                        {{ $value->value }}
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="flex flex-col items-end gap-2 shrink-0">
                    <x-status-badge :tone="match($submission->status) { 'validated' => 'success', 'pending' => 'gold', 'rejected' => 'destructive', 'suspended' => 'muted' }">
                        {{ $submission->statusLabel() }}
                    </x-status-badge>
                    <div class="flex gap-1.5">
                        @if ($submission->status !== 'validated')
                            <button type="button" wire:click="validateSubmission({{ $submission->id }})" class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-deep">Valider</button>
                        @endif
                        @if ($submission->status !== 'rejected')
                            <button type="button" wire:click="rejectSubmission({{ $submission->id }})" class="rounded-lg bg-destructive px-3 py-1.5 text-xs font-semibold text-white hover:brightness-95">Rejeter</button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <x-empty-state icon="clipboard" title="Aucune inscription" />
        @endforelse
    </div>

    <div class="mt-4">{{ $submissions->links() }}</div>
</div>
