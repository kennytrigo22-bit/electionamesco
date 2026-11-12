<?php

use App\Models\Form;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin', ['title' => 'Recensement'])] class extends Component
{
    public bool $showCreate = false;

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

        \App\Models\ActivityLog::record('form.created', "Formulaire « {$form->title} » créé", $form);

        $this->redirect(route('admin.forms.builder', $form), navigate: true);
    }

    public function toggleStatus(Form $form): void
    {
        $form->update(['status' => $form->status === 'open' ? 'closed' : 'open']);
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Publier', 'open' => 'Fermer', 'closed' => 'Rouvrir',
        };
    }

    public function with(): array
    {
        return ['forms' => Form::withCount('submissions')->latest()->get()];
    }
}; ?>

<div>
    <x-page-header title="Recensement" description="Créez des formulaires d'inscription dynamiques pour constituer le fichier électoral.">
        <x-slot:actions>
            <button type="button" wire:click="$toggle('showCreate')" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-deep">
                <x-icon name="plus" class="size-4" /> Nouveau formulaire
            </button>
        </x-slot:actions>
    </x-page-header>

    @if ($showCreate)
        <div class="mb-6 rounded-xl border border-border bg-card p-5 shadow-card">
            <h2 class="text-sm font-semibold mb-3">Nouveau formulaire d'inscription</h2>
            <form wire:submit="create" class="space-y-4">
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Titre</label>
                    <input type="text" wire:model="title" class="mt-1 block w-full rounded-lg border-border text-sm" placeholder="Ex: Recensement 2026 – 2027">
                    @error('title') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Description</label>
                    <textarea wire:model="description" rows="2" class="mt-1 block w-full rounded-lg border-border text-sm"></textarea>
                </div>
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-deep">Créer et configurer les champs</button>
            </form>
        </div>
    @endif

    <div class="rounded-xl border border-border bg-card shadow-card divide-y divide-border">
        @forelse ($forms as $form)
            <div class="p-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="font-semibold">{{ $form->title }}</p>
                    <p class="text-sm text-muted-foreground">{{ $form->submissions_count }} inscription(s)</p>
                </div>
                <div class="flex items-center gap-3">
                    <x-status-badge :tone="match($form->status) { 'open' => 'success', 'draft' => 'muted', 'closed' => 'destructive' }">
                        {{ ['draft' => 'Brouillon', 'open' => 'Ouvert', 'closed' => 'Fermé'][$form->status] }}
                    </x-status-badge>
                    @if ($form->status !== 'draft')
                        <a href="{{ route('forms.show', $form) }}" target="_blank" class="text-sm font-medium text-primary hover:underline">Lien public</a>
                    @endif
                    <a href="{{ route('admin.forms.submissions', $form) }}" wire:navigate class="text-sm font-medium text-primary hover:underline">Inscriptions</a>
                    <a href="{{ route('admin.forms.settings', $form) }}" wire:navigate class="text-sm font-medium text-primary hover:underline">Paramètres</a>
                    <a href="{{ route('admin.forms.builder', $form) }}" wire:navigate class="text-sm font-medium text-primary hover:underline">Modifier</a>
                    <button type="button" wire:click="toggleStatus({{ $form->id }})" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold hover:bg-muted">{{ $this->statusLabel($form->status) }}</button>
                </div>
            </div>
        @empty
            <x-empty-state icon="clipboard" title="Aucun formulaire" description="Créez votre premier formulaire de recensement." />
        @endforelse
    </div>
</div>
