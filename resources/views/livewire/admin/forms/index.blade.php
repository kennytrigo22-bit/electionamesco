<?php

use App\Models\Form;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $title = '';

    public string $description = '';

    public function create(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $slug = Str::slug($this->title).'-'.Str::random(6);

        $form = Form::create([
            'title' => $this->title,
            'description' => $this->description,
            'slug' => $slug,
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        $this->redirect(route('admin.forms.builder', $form), navigate: true);
    }

    public function toggleStatus(Form $form): void
    {
        $form->update([
            'status' => $form->status === 'open' ? 'closed' : 'open',
        ]);
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Publier',
            'open' => 'Fermer',
            'closed' => 'Rouvrir',
        };
    }

    public function with(): array
    {
        return [
            'forms' => Form::withCount('submissions')->latest()->get(),
        ];
    }
}; ?>

<div>
    <div class="max-w-5xl mx-auto py-10 sm:px-6 lg:px-8 space-y-8">
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
            <h2 class="text-lg font-semibold mb-4">Nouveau formulaire d'inscription</h2>
            <form wire:submit="create" class="space-y-4">
                <div>
                    <x-input-label for="title" value="Titre" />
                    <x-text-input id="title" wire:model="title" class="block w-full mt-1" />
                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="description" value="Description" />
                    <textarea id="description" wire:model="description" rows="2" class="block w-full mt-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm"></textarea>
                </div>
                <x-primary-button type="submit">Créer et configurer les champs</x-primary-button>
            </form>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
            <h2 class="text-lg font-semibold mb-4">Formulaires existants</h2>
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse ($forms as $form)
                    <div class="py-4 flex items-center justify-between gap-4">
                        <div>
                            <p class="font-medium">{{ $form->title }}</p>
                            <p class="text-sm text-gray-500">
                                {{ $form->submissions_count }} inscription(s) —
                                <span class="inline-flex px-2 py-0.5 rounded text-xs
                                    @class([
                                        'bg-green-100 text-green-800' => $form->status === 'open',
                                        'bg-gray-100 text-gray-800' => $form->status === 'draft',
                                        'bg-red-100 text-red-800' => $form->status === 'closed',
                                    ])">
                                    {{ ['draft' => 'Brouillon', 'open' => 'Ouvert', 'closed' => 'Fermé'][$form->status] }}
                                </span>
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($form->status !== 'draft')
                                <a href="{{ route('forms.show', $form) }}" target="_blank" class="text-sm text-indigo-600 hover:underline">Lien public</a>
                            @endif
                            <a href="{{ route('admin.forms.submissions', $form) }}" wire:navigate class="text-sm text-indigo-600 hover:underline">Inscriptions</a>
                            <a href="{{ route('admin.forms.builder', $form) }}" wire:navigate class="text-sm text-indigo-600 hover:underline">Modifier</a>
                            <x-secondary-button wire:click="toggleStatus({{ $form->id }})">
                                {{ $this->statusLabel($form->status) }}
                            </x-secondary-button>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 py-4">Aucun formulaire pour le moment.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
