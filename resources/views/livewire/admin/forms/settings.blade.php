<?php

use App\Models\Form;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin', ['title' => 'Paramètres du formulaire'])] class extends Component
{
    public Form $form;

    public string $title = '';

    public string $description = '';

    public ?string $opens_at = null;

    public ?string $closes_at = null;

    public ?int $max_responses = null;

    public bool $allow_multiple_submissions = false;

    public bool $auto_validate = false;

    public string $confirmation_message = '';

    public function mount(Form $form): void
    {
        $this->form = $form;
        $this->title = $form->title;
        $this->description = (string) $form->description;
        $this->opens_at = $form->opens_at?->format('Y-m-d\TH:i');
        $this->closes_at = $form->closes_at?->format('Y-m-d\TH:i');
        $this->max_responses = $form->max_responses;
        $this->allow_multiple_submissions = $form->allow_multiple_submissions;
        $this->auto_validate = $form->auto_validate;
        $this->confirmation_message = (string) $form->confirmation_message;
    }

    public function save(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date'],
            'max_responses' => ['nullable', 'integer', 'min:1'],
            'confirmation_message' => ['nullable', 'string'],
        ]);

        $this->form->update([
            'title' => $this->title,
            'description' => $this->description,
            'opens_at' => $this->opens_at,
            'closes_at' => $this->closes_at,
            'max_responses' => $this->max_responses,
            'allow_multiple_submissions' => $this->allow_multiple_submissions,
            'auto_validate' => $this->auto_validate,
            'confirmation_message' => $this->confirmation_message,
        ]);

        session()->flash('status', 'Paramètres enregistrés.');
    }

    public function with(): array
    {
        $base = $this->form->submissions();

        return [
            'publicUrl' => $this->form->status !== 'draft' ? route('forms.show', $this->form) : null,
            'stats' => [
                'total' => (clone $base)->count(),
                'validated' => (clone $base)->where('status', 'validated')->count(),
                'pending' => (clone $base)->where('status', 'pending')->count(),
                'rejected' => (clone $base)->where('status', 'rejected')->count(),
            ],
        ];
    }
}; ?>

<div>
    <x-page-header title="Paramètres du formulaire" :description="$form->title">
        <x-slot:actions>
            <a href="{{ route('admin.forms.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"><x-icon name="arrow-left" class="size-4" /> Retour</a>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-success-soft text-success text-sm px-4 py-2">{{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-6">
        <x-stat-card label="Total" :value="$stats['total']" icon="users" tone="muted" />
        <x-stat-card label="Validées" :value="$stats['validated']" icon="check" tone="primary" />
        <x-stat-card label="En attente" :value="$stats['pending']" icon="clipboard" tone="gold" />
        <x-stat-card label="Rejetées" :value="$stats['rejected']" icon="x" tone="destructive" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 rounded-xl border border-border bg-card p-5 shadow-card">
            <h2 class="text-sm font-semibold mb-4">Informations générales</h2>
            <form wire:submit="save" class="space-y-4">
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Nom du formulaire</label>
                    <input type="text" wire:model="title" class="mt-1 block w-full rounded-lg border-border text-sm">
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Description</label>
                    <textarea wire:model="description" rows="2" class="mt-1 block w-full rounded-lg border-border text-sm"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-medium text-muted-foreground">Ouverture</label>
                        <input type="datetime-local" wire:model="opens_at" class="mt-1 block w-full rounded-lg border-border text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-muted-foreground">Fermeture</label>
                        <input type="datetime-local" wire:model="closes_at" class="mt-1 block w-full rounded-lg border-border text-sm">
                    </div>
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Nombre maximum de réponses</label>
                    <input type="number" min="1" wire:model="max_responses" class="mt-1 block w-full rounded-lg border-border text-sm" placeholder="Illimité">
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Message de confirmation</label>
                    <textarea wire:model="confirmation_message" rows="2" class="mt-1 block w-full rounded-lg border-border text-sm" placeholder="Votre inscription a été enregistrée."></textarea>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="allow_multiple_submissions" class="rounded border-border"> Autoriser plusieurs soumissions par personne</label>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="auto_validate" class="rounded border-border"> Validation automatique des inscriptions</label>
                </div>
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-deep">Enregistrer</button>
            </form>
        </div>

        <div class="rounded-xl border border-border bg-card p-5 shadow-card space-y-4">
            <h2 class="text-sm font-semibold">Publication</h2>
            @if ($publicUrl)
                <div class="rounded-lg border border-border p-3">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data={{ urlencode($publicUrl) }}" alt="QR code" class="mx-auto size-32 rounded">
                    <p class="mt-2 break-all text-center text-xs text-muted-foreground">{{ $publicUrl }}</p>
                    <button type="button" x-data @click="navigator.clipboard.writeText('{{ $publicUrl }}')" class="mt-2 w-full rounded-lg border border-border py-1.5 text-xs font-semibold hover:bg-muted">Copier le lien</button>
                </div>
            @else
                <p class="text-xs text-muted-foreground">Publiez le formulaire pour obtenir un lien public et un QR code.</p>
            @endif
        </div>
    </div>
</div>
