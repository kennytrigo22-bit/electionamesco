<?php

use App\Models\Form;
use App\Models\FormField;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin', ['title' => 'Constructeur de formulaire'])] class extends Component
{
    public Form $form;

    public string $newLabel = '';

    public string $newType = 'text';

    public string $newOptions = '';

    public bool $newRequired = true;

    public bool $newIsIdentityName = false;

    public function mount(Form $form): void
    {
        $this->form = $form;
    }

    public function addField(): void
    {
        $this->validate([
            'newLabel' => ['required', 'string', 'max:255'],
            'newType' => ['required', 'in:'.implode(',', array_keys(FormField::TYPE_LABELS))],
        ]);

        $options = null;
        if (in_array($this->newType, ['select', 'radio', 'checkbox'], true)) {
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

        $this->reset(['newLabel', 'newOptions', 'newIsIdentityName']);
        $this->newType = 'text';
        $this->newRequired = true;
    }

    public function removeField(FormField $field): void
    {
        abort_unless($field->form_id === $this->form->id, 403);
        $field->delete();
    }

    public function duplicateField(FormField $field): void
    {
        abort_unless($field->form_id === $this->form->id, 403);
        $copy = $field->replicate();
        $copy->label = $field->label.' (copie)';
        $copy->order = $this->form->fields()->max('order') + 1;
        $copy->save();
    }

    public function reorder(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            FormField::where('id', $id)->where('form_id', $this->form->id)->update(['order' => $index + 1]);
        }
    }

    public function with(): array
    {
        return [
            'fields' => $this->form->fields,
            'types' => FormField::TYPE_LABELS,
        ];
    }
}; ?>

<div>
    <x-page-header :title="$form->title" :description="$form->description">
        <x-slot:actions>
            <a href="{{ route('admin.forms.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"><x-icon name="arrow-left" class="size-4" /> Retour</a>
        </x-slot:actions>
    </x-page-header>

    <div class="rounded-xl border border-border bg-card p-5 shadow-card">
        <h2 class="text-sm font-semibold mb-4">Champs du formulaire</h2>

        <div class="space-y-2 mb-6" x-data="{
                drag: null,
                onDrop(id) {
                    if (this.drag === null || this.drag === id) return;
                    let items = Array.from($el.querySelectorAll('[data-field-id]')).map(el => el.dataset.fieldId);
                    $wire.reorder(items);
                }
            }">
            @forelse ($fields as $field)
                <div data-field-id="{{ $field->id }}" draggable="true"
                     @dragstart="drag = {{ $field->id }}"
                     @dragover.prevent
                     @drop="onDrop({{ $field->id }})"
                     class="flex items-center justify-between gap-3 rounded-lg border border-border p-3 bg-surface cursor-move">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="text-muted-foreground">⠿</span>
                        <div class="min-w-0">
                            <p class="font-medium truncate">
                                {{ $field->label }}
                                @if ($field->is_required)<span class="text-destructive">*</span>@endif
                                @if ($field->is_identity_name)<x-status-badge tone="primary" class="ml-2">Nom identifiant</x-status-badge>@endif
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ $types[$field->type] }}
                                @if ($field->hasOptions()) — {{ implode(', ', $field->effectiveOptions()) }} @endif
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button type="button" wire:click="duplicateField({{ $field->id }})" class="rounded-lg p-2 text-muted-foreground hover:bg-muted" title="Dupliquer"><x-icon name="file-text" class="size-4" /></button>
                        <button type="button" wire:click="removeField({{ $field->id }})" wire:confirm="Supprimer ce champ ?" class="rounded-lg p-2 text-destructive hover:bg-destructive-soft" title="Supprimer"><x-icon name="trash" class="size-4" /></button>
                    </div>
                </div>
            @empty
                <x-empty-state icon="clipboard" title="Aucun champ" description="Ajoutez le premier champ ci-dessous." />
            @endforelse
        </div>

        <form wire:submit="addField" class="space-y-4 border-t border-border pt-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Libellé du champ</label>
                    <input type="text" wire:model="newLabel" class="mt-1 block w-full rounded-lg border-border text-sm" placeholder="Ex: Nom complet">
                    @error('newLabel') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Type de champ</label>
                    <select wire:model.live="newType" class="mt-1 block w-full rounded-lg border-border text-sm">
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if (in_array($newType, ['select', 'radio', 'checkbox'], true))
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Options (une par ligne)</label>
                    <textarea wire:model="newOptions" rows="3" class="mt-1 block w-full rounded-lg border-border text-sm"></textarea>
                </div>
            @endif

            @if (in_array($newType, ['country', 'institution', 'level'], true))
                <p class="text-xs text-muted-foreground">Ce type propose automatiquement une liste d'options prédéfinie.</p>
            @endif

            @if ($newType === 'image')
                <p class="text-xs text-muted-foreground">Les administrateurs verront un aperçu visuel du fichier téléversé.</p>
            @endif

            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="newRequired" class="rounded border-border"> Champ obligatoire</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="newIsIdentityName" class="rounded border-border"> Utiliser comme nom de l'électeur</label>
            </div>

            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-deep">
                <x-icon name="plus" class="size-4" /> Ajouter le champ
            </button>
        </form>
    </div>
</div>
