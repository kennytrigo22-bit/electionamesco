<?php

use App\Models\Election;
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

        $election = Election::create([
            'title' => $this->title,
            'description' => $this->description,
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        $this->redirect(route('admin.elections.builder', $election), navigate: true);
    }

    public function with(): array
    {
        return [
            'elections' => Election::withCount(['voters', 'ballots'])->latest()->get(),
        ];
    }
}; ?>

<div>
    <div class="max-w-5xl mx-auto py-10 sm:px-6 lg:px-8 space-y-8">
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
            <h2 class="text-lg font-semibold mb-4">Nouvelle élection</h2>
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
                <x-primary-button type="submit">Créer et configurer les urnes</x-primary-button>
            </form>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
            <h2 class="text-lg font-semibold mb-4">Élections</h2>
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse ($elections as $election)
                    <div class="py-4 flex items-center justify-between gap-4">
                        <div>
                            <p class="font-medium">{{ $election->title }}</p>
                            <p class="text-sm text-gray-500">
                                {{ $election->ballots_count }} urne(s) — {{ $election->voters_count }} électeur(s) —
                                <span class="inline-flex px-2 py-0.5 rounded text-xs
                                    @class([
                                        'bg-green-100 text-green-800' => $election->status === 'open',
                                        'bg-gray-100 text-gray-800' => $election->status === 'draft',
                                        'bg-blue-100 text-blue-800' => $election->status === 'scheduled',
                                        'bg-red-100 text-red-800' => $election->status === 'closed',
                                    ])">
                                    {{ ['draft' => 'Brouillon', 'scheduled' => 'Programmée', 'open' => 'Ouverte', 'closed' => 'Clôturée'][$election->status] }}
                                </span>
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.elections.builder', $election) }}" wire:navigate class="text-sm text-indigo-600 hover:underline">Urnes</a>
                            <a href="{{ route('admin.elections.voters', $election) }}" wire:navigate class="text-sm text-indigo-600 hover:underline">Électeurs</a>
                            <a href="{{ route('admin.elections.results', $election) }}" wire:navigate class="text-sm text-indigo-600 hover:underline">Résultats</a>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 py-4">Aucune élection pour le moment.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
