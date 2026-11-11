<?php

use App\Models\Form;
use App\Models\FormField;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Form $form;

    public string $newLabel = '';

    public string $newType = 'text';

    public string $newOptions = '';

    public bool $newRequired = true;

    public bool $newIsIdentityName = false;

    public array $types = [
        'text' => 'Texte court',
        'textarea' => 'Texte long',
        'email' => 'Email',
        'tel' => 'Téléphone',
        'number' => 'Nombre',
        'date' => 'Date',
        'select' => 'Liste déroulante',
        'radio' => 'Choix unique',
        'checkbox' => 'Cases à cocher',
        'file' => 'Fichier',
    ];

    public function mount(Form $form): void
    {
        $this->form = $form;
    }

    public function addField(): void
    {
        $this->validate([
            'newLabel' => ['required', 'string', 'max:255'],
            'newType' => ['required', 'in:'.implode(',', array_keys($this->types))],
        ]);

        $options = null;
        if (in_array($this->newType, FormField::TYPES_WITH_OPTIONS, true)) {
            $options = array_values(array_filter(array_map('trim', explode("\n", $this->newOptions))));
        }

        $this->form->fields()->create([
            'label' => $this->newLabel,
            'type' => $this->newType,
            'options' => $options,
            'is_required' => $this->newRequired,
            'is_identity_name' => $this->newIsIdentityName,
            'order' => $this->form->fields()->max('order') + 1,
        ]);

        $this->reset(['newLabel', 'newType', 'newOptions', 'newRequired', 'newIsIdentityName']);
        $this->newType = 'text';
        $this->newRequired = true;
    }

    public function removeField(FormField $field): void
    {
        $field->delete();
    }

    public function moveUp(FormField $field): void
    {
        $previous = $this->form->fields()->where('order', '<', $field->order)->orderByDesc('order')->first();
        if ($previous) {
            [$field->order, $previous->order] = [$previous->order, $field->order];
            $field->save();
            $previous->save();
        }
    }

    public function moveDown(FormField $field): void
    {
        $next = $this->form->fields()->where('order', '>', $field->order)->orderBy('order')->first();
        if ($next) {
            [$field->order, $next->order] = [$next->order, $field->order];
            $field->save();
            $next->save();
        }
    }

    public function with(): array
    {
        return [
            'fields' => $this->form->fields,
        ];
    }
}; ?>

<div>
    <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8 space-y-8">
        <div>
            <h1 class="text-xl font-semibold">{{ $form->title }}</h1>
            <p class="text-sm text-gray-500">{{ $form->description }}</p>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
            <h2 class="text-lg font-semibold mb-4">Champs du formulaire</h2>

            <div class="space-y-3 mb-6">
                @forelse ($fields as $field)
                    <div class="flex items-center justify-between border rounded-md p-3 dark:border-gray-700">
                        <div>
                            <p class="font-medium">
                                {{ $field->label }}
                                @if ($field->is_required)<span class="text-red-500">*</span>@endif
                                @if ($field->is_identity_name)<span class="ml-2 text-xs bg-indigo-100 text-indigo-800 px-2 py-0.5 rounded">Nom identifiant</span>@endif
                            </p>
                            <p class="text-sm text-gray-500">
                                {{ $types[$field->type] }}
                                @if ($field->hasOptions()) — {{ implode(', ', $field->options ?? []) }} @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="moveUp({{ $field->id }})" class="text-gray-400 hover:text-gray-700">&uarr;</button>
                            <button type="button" wire:click="moveDown({{ $field->id }})" class="text-gray-400 hover:text-gray-700">&darr;</button>
                            <x-danger-button wire:click="removeField({{ $field->id }})" wire:confirm="Supprimer ce champ ?">Supprimer</x-danger-button>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Aucun champ pour le moment. Ajoutez le premier champ ci-dessous.</p>
                @endforelse
            </div>

            <form wire:submit="addField" class="space-y-4 border-t pt-6 dark:border-gray-700">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label value="Libellé du champ" />
                        <x-text-input wire:model="newLabel" class="block w-full mt-1" placeholder="Ex: Nom complet" />
                        <x-input-error :messages="$errors->get('newLabel')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label value="Type de champ" />
                        <select wire:model.live="newType" class="block w-full mt-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if (in_array($newType, \App\Models\FormField::TYPES_WITH_OPTIONS, true))
                    <div>
                        <x-input-label value="Options (une par ligne)" />
                        <textarea wire:model="newOptions" rows="3" class="block w-full mt-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm"></textarea>
                    </div>
                @endif

                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="newRequired" class="rounded border-gray-300">
                        Champ obligatoire
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="newIsIdentityName">
                        Utiliser comme nom de l'électeur
                    </label>
                </div>

                <x-primary-button type="submit">Ajouter le champ</x-primary-button>
            </form>
        </div>

        <a href="{{ route('admin.forms.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">&larr; Retour aux formulaires</a>
    </div>
</div>
