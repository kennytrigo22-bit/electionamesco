<?php

use App\Models\Form;
use App\Models\FormSubmission;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public Form $form;

    public string $search = '';

    public string $statusFilter = 'all';

    public function mount(Form $form): void
    {
        $this->form = $form->load('fields');
    }

    public function validateSubmission(FormSubmission $submission): void
    {
        abort_unless($submission->form_id === $this->form->id, 403);

        $submission->update([
            'status' => 'validated',
            'validated_by' => auth()->id(),
            'validated_at' => now(),
        ]);
    }

    public function rejectSubmission(FormSubmission $submission): void
    {
        abort_unless($submission->form_id === $this->form->id, 403);

        $submission->update([
            'status' => 'rejected',
            'validated_by' => auth()->id(),
            'validated_at' => now(),
        ]);
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

        return [
            'submissions' => $submissions,
        ];
    }
}; ?>

<div>
    <div class="max-w-5xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
        <div>
            <h1 class="text-xl font-semibold">Inscriptions — {{ $form->title }}</h1>
        </div>

        <div class="flex flex-wrap items-center gap-4">
            <x-text-input wire:model.live.debounce.300ms="search" placeholder="Rechercher par nom..." class="max-w-xs" />
            <select wire:model.live="statusFilter" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                <option value="all">Tous les statuts</option>
                <option value="pending">En attente</option>
                <option value="validated">Validées</option>
                <option value="rejected">Rejetées</option>
            </select>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg divide-y divide-gray-200 dark:divide-gray-700">
            @forelse ($submissions as $submission)
                <div class="p-4 flex items-start justify-between gap-4">
                    <div>
                        <p class="font-medium">{{ $submission->displayName() }}</p>
                        <p class="text-sm text-gray-500">
                            Soumis le {{ $submission->submitted_at->format('d/m/Y H:i') }}
                        </p>
                        <div class="mt-2 text-sm text-gray-600 dark:text-gray-400 space-y-1">
                            @foreach ($submission->values as $value)
                                <div><span class="font-medium">{{ $value->field->label }}:</span> {{ $value->value }}</div>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-2 shrink-0">
                        <span class="inline-flex px-2 py-0.5 rounded text-xs
                            @class([
                                'bg-yellow-100 text-yellow-800' => $submission->status === 'pending',
                                'bg-green-100 text-green-800' => $submission->status === 'validated',
                                'bg-red-100 text-red-800' => $submission->status === 'rejected',
                            ])">
                            {{ ['pending' => 'En attente', 'validated' => 'Validée', 'rejected' => 'Rejetée'][$submission->status] }}
                        </span>
                        @if ($submission->status !== 'validated')
                            <x-primary-button wire:click="validateSubmission({{ $submission->id }})">Valider</x-primary-button>
                        @endif
                        @if ($submission->status !== 'rejected')
                            <x-danger-button wire:click="rejectSubmission({{ $submission->id }})">Rejeter</x-danger-button>
                        @endif
                    </div>
                </div>
            @empty
                <p class="p-4 text-sm text-gray-500">Aucune inscription.</p>
            @endforelse
        </div>

        {{ $submissions->links() }}

        <a href="{{ route('admin.forms.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">&larr; Retour aux formulaires</a>
    </div>
</div>
