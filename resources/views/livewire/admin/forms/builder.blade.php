<?php

use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSection;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin', ['title' => 'Constructeur de formulaire'])] class extends Component
{
    public Form $form;

    public string $mode = 'edit';

    public ?int $activeFieldId = null;

    public array $draft = [];

    public bool $showRules = false;

    public function mount(Form $form): void
    {
        $this->form = $form;

        $defaultSection = $this->form->sections()->first();

        if (! $defaultSection) {
            $defaultSection = $this->form->sections()->create(['title' => 'Section 1', 'order' => 1]);
        }

        $this->form->fields()->whereNull('form_section_id')->update(['form_section_id' => $defaultSection->id]);
    }

    protected function ensureOwned(FormField|FormSection $model): void
    {
        $formId = $model instanceof FormField ? $model->form_id : $model->form_id;
        abort_unless($formId === $this->form->id, 403);
    }

    public function updateTitle(string $value): void
    {
        $this->form->update(['title' => $value]);
    }

    public function updateDescription(string $value): void
    {
        $this->form->update(['description' => $value]);
    }

    public function togglePublished(): void
    {
        $this->form->update(['status' => $this->form->status === 'open' ? 'draft' : 'open']);
    }

    public function addSection(): void
    {
        $this->form->sections()->create([
            'title' => 'Nouvelle section',
            'order' => $this->form->sections()->max('order') + 1,
        ]);
    }

    public function renameSection(FormSection $section, string $title): void
    {
        $this->ensureOwned($section);
        $section->update(['title' => trim($title) ?: $section->title]);
    }

    public function removeSection(FormSection $section): void
    {
        $this->ensureOwned($section);

        $other = $this->form->sections()->where('id', '!=', $section->id)->orderBy('order')->first();

        if (! $other) {
            return;
        }

        $section->fields()->update(['form_section_id' => $other->id]);
        $section->delete();
    }

    public function selectField(int $fieldId): void
    {
        $field = FormField::findOrFail($fieldId);
        $this->ensureOwned($field);

        $this->activeFieldId = $field->id;
        $this->showRules = (bool) $field->condition_field_id;
        $this->draft = [
            'label' => $field->label,
            'type' => $field->type,
            'options' => implode("\n", $field->options ?? []),
            'is_required' => $field->is_required,
            'is_identity_name' => $field->is_identity_name,
            'condition_field_id' => $field->condition_field_id,
            'condition_value' => $field->condition_value,
        ];
    }

    public function clearActive(): void
    {
        $this->activeFieldId = null;
        $this->draft = [];
        $this->showRules = false;
    }

    public function updated(string $name): void
    {
        if (! str_starts_with($name, 'draft.') || ! $this->activeFieldId) {
            return;
        }

        $field = FormField::find($this->activeFieldId);
        if (! $field) {
            return;
        }

        $options = FormField::TYPES_WITH_OPTIONS;
        $hasOptions = in_array($this->draft['type'], $options, true) && ! in_array($this->draft['type'], ['country', 'institution', 'level'], true);

        $field->update([
            'label' => $this->draft['label'] ?: $field->label,
            'type' => $this->draft['type'],
            'options' => $hasOptions ? array_values(array_filter(array_map('trim', explode("\n", $this->draft['options'] ?? '')))) : null,
            'is_required' => (bool) $this->draft['is_required'],
            'is_identity_name' => (bool) $this->draft['is_identity_name'],
            'condition_field_id' => $this->draft['condition_field_id'] ?: null,
            'condition_value' => $this->draft['condition_field_id'] ? ($this->draft['condition_value'] ?? null) : null,
        ]);
    }

    public function addField(int $sectionId, string $type = 'text'): void
    {
        $section = FormSection::findOrFail($sectionId);
        $this->ensureOwned($section);

        $field = $this->form->fields()->create([
            'form_section_id' => $section->id,
            'label' => FormField::TYPE_LABELS[$type] ?? 'Nouvelle question',
            'type' => $type,
            'options' => in_array($type, ['select', 'radio', 'checkbox'], true) ? ['Option 1', 'Option 2'] : null,
            'is_required' => false,
            'order' => $this->form->fields()->max('order') + 1,
        ]);

        $this->selectField($field->id);
    }

    public function duplicateField(FormField $field): void
    {
        $this->ensureOwned($field);
        $copy = $field->replicate();
        $copy->label = $field->label.' (copie)';
        $copy->order = $this->form->fields()->max('order') + 1;
        $copy->save();
    }

    public function removeField(FormField $field): void
    {
        $this->ensureOwned($field);
        if ($this->activeFieldId === $field->id) {
            $this->clearActive();
        }
        $field->delete();
    }

    public function moveField(FormField $field, int $direction): void
    {
        $this->ensureOwned($field);

        $siblings = FormField::where('form_section_id', $field->form_section_id)->orderBy('order')->get();
        $index = $siblings->search(fn ($f) => $f->id === $field->id);
        $targetIndex = $index + $direction;

        if ($targetIndex < 0 || $targetIndex >= $siblings->count()) {
            return;
        }

        $other = $siblings[$targetIndex];
        [$orderA, $orderB] = [$field->order, $other->order];
        $field->update(['order' => $orderB]);
        $other->update(['order' => $orderA]);
    }

    public function with(): array
    {
        $allFields = $this->form->fields;

        return [
            'sections' => $this->form->sections()->with('fields')->get(),
            'allFields' => $allFields,
            'optionFields' => $allFields->filter(fn (FormField $f) => $f->hasOptions()),
            'types' => FormField::TYPE_LABELS,
            'groups' => collect(FormField::TYPE_GROUPS)->unique()->values(),
            'typeGroups' => FormField::TYPE_GROUPS,
            'totalRequired' => $allFields->where('is_required', true)->count(),
            'publicUrl' => route('forms.show', $this->form),
        ];
    }
}; ?>

<div class="-mx-4 -my-6 md:-mx-8 md:-my-8">
    <div class="sticky top-16 z-20 flex flex-wrap items-center gap-3 border-b border-border bg-card px-4 py-3 md:px-8">
        <div class="flex min-w-0 items-center gap-3">
            <span class="hidden size-9 items-center justify-center rounded-lg bg-primary-soft text-primary sm:inline-flex"><x-icon name="rows" class="size-4" /></span>
            <div class="min-w-0">
                <input value="{{ $form->title }}" wire:change="updateTitle($event.target.value)"
                       class="w-full min-w-0 truncate rounded bg-transparent text-[15px] font-semibold text-foreground outline-none focus:bg-surface md:w-96" aria-label="Titre du formulaire">
                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                    <x-icon name="check" class="size-3.5 text-primary" /> Modifications enregistrées
                    <span>·</span>
                    <span>{{ $allFields->count() }} questions</span>
                </div>
            </div>
        </div>
        <div class="ml-auto flex flex-wrap items-center gap-2">
            <div class="inline-flex rounded-lg border border-border bg-surface p-0.5">
                <button type="button" wire:click="$set('mode', 'edit')" class="inline-flex h-8 items-center gap-1.5 rounded-md px-3 text-xs font-semibold {{ $mode === 'edit' ? 'bg-card text-foreground shadow-card' : 'text-muted-foreground' }}"><x-icon name="pencil" class="size-3.5" /> Édition</button>
                <button type="button" wire:click="$set('mode', 'preview')" class="inline-flex h-8 items-center gap-1.5 rounded-md px-3 text-xs font-semibold {{ $mode === 'preview' ? 'bg-card text-foreground shadow-card' : 'text-muted-foreground' }}"><x-icon name="eye" class="size-3.5" /> Aperçu</button>
            </div>
            <a href="{{ route('admin.forms.settings', $form) }}" wire:navigate class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-border px-3 text-xs font-semibold hover:bg-muted"><x-icon name="settings" class="size-3.5" /> Paramètres</a>
            <button type="button" x-data @click="navigator.clipboard.writeText('{{ $publicUrl }}'); $tooltip='Copié'" class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-border px-3 text-xs font-semibold hover:bg-muted"><x-icon name="link" class="size-3.5" /> Copier le lien</button>
            <label class="flex h-8 items-center gap-2 rounded-lg border border-border px-3 text-xs font-semibold">
                <input type="checkbox" class="sr-only peer" {{ $form->status === 'open' ? 'checked' : '' }} wire:click="togglePublished">
                <span class="relative inline-flex h-4 w-7 items-center rounded-full bg-muted peer-checked:bg-primary transition">
                    <span class="size-3 translate-x-0.5 rounded-full bg-white transition peer-checked:translate-x-3.5"></span>
                </span>
                {{ $form->status === 'open' ? 'Publié' : 'Brouillon' }}
            </label>
        </div>
    </div>

    <div class="grid gap-6 px-4 py-6 md:px-8 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="mx-auto w-full max-w-[760px]">
            @if ($mode === 'preview')
                <div class="rounded-2xl border border-border bg-surface p-3 shadow-card md:p-6">
                    <div class="mb-4 flex items-center justify-between text-xs text-muted-foreground">
                        <span class="inline-flex items-center gap-1.5"><x-icon name="eye" class="size-3.5" /> Aperçu tel que vu par les membres</span>
                        <a href="{{ $publicUrl }}" target="_blank" class="font-semibold text-primary hover:underline">Ouvrir la page publique →</a>
                    </div>
                    <div class="overflow-hidden rounded-xl border border-border bg-card">
                        <div class="tricolor h-1.5"></div>
                        <div class="p-6">
                            <h2 class="text-xl font-bold">{{ $form->title }}</h2>
                            <p class="mt-1 text-sm text-muted-foreground">{{ $form->description }}</p>
                        </div>
                    </div>
                    @foreach ($sections as $i => $section)
                        <div class="mt-4 rounded-xl border border-border bg-card p-6">
                            <p class="text-xs font-semibold uppercase tracking-wider text-primary">Étape {{ $i + 1 }} sur {{ $sections->count() }}</p>
                            <h3 class="mt-1 text-lg font-bold">{{ $section->title }}</h3>
                            @if ($section->description)<p class="text-sm text-muted-foreground">{{ $section->description }}</p>@endif
                            <div class="mt-5 space-y-4">
                                @foreach ($section->fields as $field)
                                    <div>
                                        @if ($field->condition_field_id)
                                            <p class="mb-1 inline-flex items-center gap-1 rounded bg-gold-soft px-1.5 py-px text-[11px] font-medium text-gold-foreground"><x-icon name="git-branch" class="size-3" /> Visible si une condition est remplie</p>
                                        @endif
                                        <label class="text-sm font-medium">{{ $field->label }}@if($field->is_required)<span class="text-destructive"> *</span>@endif</label>
                                        <p class="text-xs text-muted-foreground mt-0.5">{{ $types[$field->type] }}@if($field->hasOptions()) — {{ implode(', ', $field->effectiveOptions()) }}@endif</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="overflow-hidden rounded-xl border border-border bg-card shadow-card">
                    <div class="tricolor h-2"></div>
                    <div class="space-y-2 p-6">
                        <input value="{{ $form->title }}" wire:change="updateTitle($event.target.value)" class="w-full rounded-md border-b border-transparent bg-transparent pb-1 text-2xl font-bold text-foreground outline-none focus:border-primary">
                        <textarea rows="2" wire:change="updateDescription($event.target.value)" class="w-full resize-none rounded-md border-b border-transparent bg-transparent text-sm text-muted-foreground outline-none focus:border-primary">{{ $form->description }}</textarea>
                        <div class="flex flex-wrap gap-2 pt-1">
                            <x-status-badge tone="{{ $form->status === 'open' ? 'success' : 'muted' }}">{{ $form->status === 'open' ? 'Publié' : 'Brouillon' }}</x-status-badge>
                            <x-status-badge tone="muted" :dot="false">{{ $sections->count() }} sections</x-status-badge>
                            <x-status-badge tone="gold" :dot="false">{{ $totalRequired }} champs obligatoires</x-status-badge>
                        </div>
                    </div>
                </div>

                @foreach ($sections as $si => $section)
                    <div class="mt-6" wire:key="section-{{ $section->id }}">
                        <div class="mb-3 flex items-center gap-3">
                            <span class="inline-flex h-6 items-center rounded-md bg-primary px-2 text-xs font-bold text-white">Section {{ $si + 1 }}</span>
                            <input value="{{ $section->title }}" wire:change="renameSection({{ $section->id }}, $event.target.value)" class="min-w-0 flex-1 rounded bg-transparent text-[15px] font-semibold text-foreground outline-none focus:bg-card">
                            <span class="text-xs text-muted-foreground">{{ $section->fields->count() }} questions</span>
                            @if ($sections->count() > 1)
                                <button type="button" wire:click="removeSection({{ $section->id }})" wire:confirm="Supprimer cette section ? Ses questions seront déplacées." class="rounded p-1 text-muted-foreground hover:bg-danger-soft hover:text-destructive"><x-icon name="trash" class="size-4" /></button>
                            @endif
                        </div>

                        <div class="space-y-3">
                            @foreach ($section->fields as $field)
                                <div wire:key="field-{{ $field->id }}" class="group relative rounded-xl border border-border bg-card shadow-card transition {{ $activeFieldId === $field->id ? 'border-primary/40 ring-4 ring-primary/10' : 'hover:border-primary/30' }}">
                                    @if ($activeFieldId !== $field->id)
                                        <button type="button" wire:click="selectField({{ $field->id }})" class="block w-full px-5 py-4 text-left">
                                            <div class="mb-2 flex items-center gap-2 text-[11px] font-medium text-muted-foreground">
                                                {{ $types[$field->type] }}
                                                @if ($field->condition_field_id)
                                                    <span class="inline-flex items-center gap-1 rounded bg-gold-soft px-1.5 py-px text-gold-foreground"><x-icon name="git-branch" class="size-3" /> Conditionnelle</span>
                                                @endif
                                                @if ($field->is_identity_name)
                                                    <span class="inline-flex items-center gap-1 rounded bg-primary-soft px-1.5 py-px text-primary-deep">Nom identifiant</span>
                                                @endif
                                            </div>
                                            <p class="font-medium text-foreground">{{ $field->label }}@if($field->is_required)<span class="text-destructive"> *</span>@endif</p>
                                            @if ($field->hasOptions())
                                                <p class="mt-1 text-xs text-muted-foreground">{{ implode(', ', $field->effectiveOptions()) }}</p>
                                            @endif
                                        </button>
                                    @else
                                        <div class="px-5 pb-4 pt-4">
                                            <div class="flex flex-col gap-3 sm:flex-row">
                                                <input autofocus wire:model.live.debounce.400ms="draft.label" placeholder="Intitulé de la question" class="h-11 flex-1 rounded-lg border-b-2 border-primary bg-surface px-3 text-[15px] font-semibold text-foreground outline-none">
                                                <select wire:model.live="draft.type" class="h-11 rounded-lg border border-border bg-card px-3 text-sm sm:w-52">
                                                    @foreach ($types as $value => $label)
                                                        <option value="{{ $value }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            @if (in_array($draft['type'] ?? '', ['select', 'radio', 'checkbox'], true))
                                                <div class="mt-3">
                                                    <label class="text-xs font-semibold text-foreground">Options (une par ligne)</label>
                                                    <textarea wire:model.live.debounce.400ms="draft.options" rows="3" class="mt-1 block w-full rounded-lg border border-border text-sm"></textarea>
                                                </div>
                                            @elseif (in_array($draft['type'] ?? '', ['country', 'institution', 'level'], true))
                                                <p class="mt-3 text-xs text-muted-foreground">Ce type propose automatiquement une liste d'options prédéfinie.</p>
                                            @elseif (($draft['type'] ?? '') === 'image')
                                                <p class="mt-3 text-xs text-muted-foreground">Les administrateurs verront un aperçu visuel du fichier téléversé.</p>
                                            @endif

                                            <div class="mt-4">
                                                <button type="button" wire:click="$set('showRules', {{ $showRules ? 'false' : 'true' }})" class="inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-xs font-semibold {{ $showRules ? 'bg-primary-soft text-primary-deep' : 'text-muted-foreground hover:bg-surface' }}">
                                                    <x-icon name="shield-check" class="size-3.5" /> Règles et conditions
                                                </button>
                                            </div>

                                            @if ($showRules)
                                                <div class="mt-3 rounded-lg border border-border bg-surface p-3">
                                                    <label class="flex items-center gap-1.5 text-xs font-semibold text-foreground"><x-icon name="git-branch" class="size-3.5 text-gold-foreground" /> Afficher cette question uniquement si…</label>
                                                    <div class="mt-1 flex flex-col gap-2 sm:flex-row">
                                                        <select wire:model.live="draft.condition_field_id" class="h-9 flex-1 rounded-md border border-border bg-card px-2 text-sm">
                                                            <option value="">Toujours afficher</option>
                                                            @foreach ($optionFields as $of)
                                                                @continue($of->id === $field->id)
                                                                <option value="{{ $of->id }}">{{ $of->label }}</option>
                                                            @endforeach
                                                        </select>
                                                        @if (!empty($draft['condition_field_id']))
                                                            @php $src = $optionFields->firstWhere('id', (int) $draft['condition_field_id']); @endphp
                                                            <select wire:model.live="draft.condition_value" class="h-9 flex-1 rounded-md border border-border bg-card px-2 text-sm">
                                                                @foreach ($src?->effectiveOptions() ?? [] as $option)
                                                                    <option value="{{ $option }}">{{ $option }}</option>
                                                                @endforeach
                                                            </select>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif

                                            <div class="mt-4 flex flex-wrap items-center justify-end gap-1 border-t border-border pt-3">
                                                <button type="button" wire:click="clearActive" class="mr-auto text-xs font-semibold text-muted-foreground hover:text-foreground">Fermer</button>
                                                <button type="button" wire:click="moveField({{ $field->id }}, -1)" title="Monter" class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-surface"><x-icon name="up" class="size-4" /></button>
                                                <button type="button" wire:click="moveField({{ $field->id }}, 1)" title="Descendre" class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-surface"><x-icon name="down" class="size-4" /></button>
                                                <button type="button" wire:click="duplicateField({{ $field->id }})" title="Dupliquer" class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-surface"><x-icon name="copy" class="size-4" /></button>
                                                <button type="button" wire:click="removeField({{ $field->id }})" wire:confirm="Supprimer cette question ?" title="Supprimer" class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-danger-soft hover:text-destructive"><x-icon name="trash" class="size-4" /></button>
                                                <span class="mx-2 h-6 w-px bg-border"></span>
                                                <label class="flex items-center gap-2 text-xs font-semibold text-foreground">Obligatoire <input type="checkbox" wire:model.live="draft.is_required" class="rounded border-border"></label>
                                                <label class="flex items-center gap-2 text-xs font-semibold text-foreground">Nom identifiant <input type="checkbox" wire:model.live="draft.is_identity_name" class="rounded border-border"></label>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach

                            <button type="button" wire:click="addField({{ $section->id }}, 'text')" class="flex h-12 w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-border text-xs font-medium text-muted-foreground hover:bg-card hover:text-foreground">
                                <x-icon name="plus" class="size-3.5" /> Ajouter une question à « {{ $section->title }} »
                            </button>
                        </div>
                    </div>
                @endforeach

                <button type="button" wire:click="addSection" class="mt-2 flex w-full items-center justify-center gap-2 rounded-xl border border-border bg-card py-3 text-sm font-semibold text-primary shadow-card hover:bg-primary-soft/50">
                    <x-icon name="plus" class="size-4" /> Ajouter une section
                </button>
            @endif
        </div>

        <aside class="lg:sticky lg:top-[136px] lg:h-[calc(100vh-160px)]">
            <div class="flex h-full flex-col overflow-hidden rounded-xl border border-border bg-card shadow-card">
                <div x-data="{ tab: 'champs' }" class="flex h-full flex-col">
                    <div class="m-3 mb-0 grid grid-cols-2 gap-1 rounded-lg bg-surface p-1">
                        <button type="button" @click="tab = 'champs'" :class="tab === 'champs' ? 'bg-card shadow-card' : ''" class="rounded-md px-3 py-1.5 text-xs font-semibold">Composants</button>
                        <button type="button" @click="tab = 'parametres'" :class="tab === 'parametres' ? 'bg-card shadow-card' : ''" class="rounded-md px-3 py-1.5 text-xs font-semibold">Paramètres</button>
                    </div>

                    <div x-show="tab === 'champs'" class="flex-1 overflow-y-auto p-4 pt-3">
                        <p class="mb-3 text-xs text-muted-foreground">Cliquez sur un composant pour l'ajouter à la dernière section.</p>
                        @foreach ($groups as $group)
                            <div class="mb-4">
                                <div class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">{{ $group }}</div>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach ($typeGroups as $type => $g)
                                        @continue($g !== $group)
                                        <button type="button" wire:click="addField({{ $sections->last()->id }}, '{{ $type }}')" class="flex items-center gap-2 rounded-lg border border-border bg-surface px-2.5 py-2 text-left text-xs font-medium text-foreground hover:border-primary/40 hover:bg-primary-soft/50">
                                            <span class="truncate">{{ $types[$type] }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div x-show="tab === 'parametres'" class="flex-1 space-y-4 overflow-y-auto p-4 pt-3 text-sm">
                        <div class="rounded-lg border border-border bg-surface p-3">
                            <p class="text-xs font-semibold text-foreground">Lien public</p>
                            <p class="mt-1 break-all font-mono text-[11px] text-muted-foreground">{{ $publicUrl }}</p>
                            <div class="mt-2 flex gap-2">
                                <button type="button" x-data @click="navigator.clipboard.writeText('{{ $publicUrl }}')" class="inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 text-xs font-semibold hover:bg-muted"><x-icon name="copy" class="size-3.5" /> Copier</button>
                                <a href="{{ $publicUrl }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold text-primary hover:bg-primary-soft"><x-icon name="link" class="size-3.5" /> Ouvrir</a>
                            </div>
                        </div>
                        <a href="{{ route('admin.forms.settings', $form) }}" wire:navigate class="flex w-full items-center justify-center gap-2 rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-muted"><x-icon name="settings" class="size-3.5" /> Tous les paramètres</a>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>
