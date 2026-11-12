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

    public string $access = 'public';

    public bool $collect_email = true;

    public bool $one_response_per_person = true;

    public bool $show_progress_bar = true;

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
        $this->access = $form->access;
        $this->collect_email = $form->collect_email;
        $this->one_response_per_person = $form->one_response_per_person;
        $this->show_progress_bar = $form->show_progress_bar;
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
            'access' => $this->access,
            'collect_email' => $this->collect_email,
            'one_response_per_person' => $this->one_response_per_person,
            'show_progress_bar' => $this->show_progress_bar,
            'auto_validate' => $this->auto_validate,
            'confirmation_message' => $this->confirmation_message,
        ]);

        session()->flash('status', 'Paramètres enregistrés.');
    }

    public function togglePublished(): void
    {
        $this->form->update(['status' => $this->form->status === 'open' ? 'draft' : 'open']);
    }

    public function with(): array
    {
        $base = $this->form->submissions();

        return [
            'publicUrl' => route('forms.show', $this->form),
            'stats' => [
                'total' => (clone $base)->count(),
                'validated' => (clone $base)->where('status', 'validated')->count(),
                'pending' => (clone $base)->where('status', 'pending')->count(),
                'rejected' => (clone $base)->where('status', 'rejected')->count(),
                'suspended' => (clone $base)->where('status', 'suspended')->count(),
            ],
        ];
    }
}; ?>

<div>
    <a href="{{ route('admin.forms.builder', $form) }}" wire:navigate class="mb-3 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground"><x-icon name="arrow-left" class="size-4" /> Retour au constructeur</a>

    <x-page-header title="Paramètres et publication" :description="$form->title">
        <x-slot:actions>
            <button type="button" wire:click="save" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-deep"><x-icon name="check" class="size-4" /> Enregistrer</button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-success-soft text-success text-sm px-4 py-2">{{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 mb-6">
        <x-stat-card label="Soumissions totales" :value="$stats['total']" icon="clipboard" tone="muted" />
        <x-stat-card label="Soumissions validées" :value="$stats['validated']" icon="check" tone="primary" />
        <x-stat-card label="En attente" :value="$stats['pending']" icon="users" tone="gold" />
        <x-stat-card label="Rejetées" :value="$stats['rejected']" icon="x" tone="destructive" />
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="space-y-6">
            <div class="rounded-xl border border-border bg-card p-5 shadow-card">
                <h2 class="text-sm font-semibold mb-4">Informations générales</h2>
                <div class="space-y-4">
                    <div>
                        <label class="text-xs font-medium text-muted-foreground">Nom du formulaire</label>
                        <input type="text" wire:model="title" class="mt-1 block w-full rounded-lg border-border text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-muted-foreground">Description</label>
                        <textarea wire:model="description" rows="3" class="mt-1 block w-full rounded-lg border-border text-sm"></textarea>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="text-xs font-medium text-muted-foreground">Date d'ouverture</label>
                            <input type="datetime-local" wire:model="opens_at" class="mt-1 block w-full rounded-lg border-border text-sm">
                        </div>
                        <div>
                            <label class="text-xs font-medium text-muted-foreground">Date de clôture</label>
                            <input type="datetime-local" wire:model="closes_at" class="mt-1 block w-full rounded-lg border-border text-sm">
                        </div>
                        <div>
                            <label class="text-xs font-medium text-muted-foreground">Nombre maximal de réponses</label>
                            <input type="number" min="1" wire:model="max_responses" class="mt-1 block w-full rounded-lg border-border text-sm" placeholder="Illimité">
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-border bg-card p-5 shadow-card">
                <h2 class="text-sm font-semibold mb-4">Accès et soumissions</h2>
                <div class="grid gap-3 sm:grid-cols-2 mb-5">
                    <label class="flex cursor-pointer gap-3 rounded-xl border p-4 {{ $access === 'public' ? 'border-primary bg-primary-soft/50' : 'border-border hover:border-primary/30' }}">
                        <input type="radio" wire:model="access" value="public" class="mt-0.5">
                        <span><span class="flex items-center gap-2 text-sm font-semibold"><x-icon name="link" class="size-4 text-primary" /> Public</span><span class="block text-xs text-muted-foreground mt-0.5">Toute personne disposant du lien peut s'inscrire.</span></span>
                    </label>
                    <label class="flex cursor-pointer gap-3 rounded-xl border p-4 {{ $access === 'restricted' ? 'border-primary bg-primary-soft/50' : 'border-border hover:border-primary/30' }}">
                        <input type="radio" wire:model="access" value="restricted" class="mt-0.5">
                        <span><span class="flex items-center gap-2 text-sm font-semibold"><x-icon name="lock" class="size-4 text-primary" /> Restreint</span><span class="block text-xs text-muted-foreground mt-0.5">Accès sur invitation ou code d'établissement.</span></span>
                    </label>
                </div>
                <div class="space-y-4">
                    <label class="flex items-start justify-between gap-3">
                        <span><span class="block text-[13px] font-medium text-foreground">Collecter les adresses e-mail</span><span class="block text-xs text-muted-foreground">Requis pour l'envoi des liens de vote.</span></span>
                        <input type="checkbox" wire:model="collect_email" class="mt-1 rounded border-border">
                    </label>
                    <label class="flex items-start justify-between gap-3">
                        <span><span class="block text-[13px] font-medium text-foreground">Une seule réponse par personne</span><span class="block text-xs text-muted-foreground">Détection par adresse e-mail.</span></span>
                        <input type="checkbox" wire:model="one_response_per_person" class="mt-1 rounded border-border">
                    </label>
                    <label class="flex items-start justify-between gap-3">
                        <span><span class="block text-[13px] font-medium text-foreground">Afficher la barre de progression</span></span>
                        <input type="checkbox" wire:model="show_progress_bar" class="mt-1 rounded border-border">
                    </label>
                    <label class="flex items-start justify-between gap-3">
                        <span><span class="block text-[13px] font-medium text-foreground">Formulaire ouvert aux inscriptions</span><span class="block text-xs text-muted-foreground">Statut du recensement visible sur la page d'accueil.</span></span>
                        <input type="checkbox" {{ $form->status === 'open' ? 'checked' : '' }} wire:click="togglePublished" class="mt-1 rounded border-border">
                    </label>
                </div>
            </div>

            <div class="rounded-xl border border-border bg-card p-5 shadow-card">
                <h2 class="text-sm font-semibold mb-4">Validation et confirmation</h2>
                <div class="grid gap-3 sm:grid-cols-2 mb-5">
                    <label class="flex cursor-pointer gap-3 rounded-xl border p-4 {{ ! $auto_validate ? 'border-primary bg-primary-soft/50' : 'border-border hover:border-primary/30' }}">
                        <input type="radio" wire:model="auto_validate" value="0" class="mt-0.5">
                        <span><span class="block text-sm font-semibold">Validation manuelle</span><span class="block text-xs text-muted-foreground mt-0.5">Un agent de recensement vérifie chaque dossier (recommandé).</span></span>
                    </label>
                    <label class="flex cursor-pointer gap-3 rounded-xl border p-4 {{ $auto_validate ? 'border-primary bg-primary-soft/50' : 'border-border hover:border-primary/30' }}">
                        <input type="radio" wire:model="auto_validate" value="1" class="mt-0.5">
                        <span><span class="block text-sm font-semibold">Validation automatique</span><span class="block text-xs text-muted-foreground mt-0.5">Les dossiers complets sont validés sans intervention.</span></span>
                    </label>
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Message de confirmation</label>
                    <textarea wire:model="confirmation_message" rows="3" class="mt-1 block w-full rounded-lg border-border text-sm" placeholder="Merci ! Votre inscription a bien été reçue."></textarea>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-xl border border-border bg-card p-5 shadow-card">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-semibold">Lien de partage</h2>
                    <x-status-badge tone="{{ $form->status === 'open' ? 'success' : 'muted' }}">{{ $form->status === 'open' ? 'En ligne' : 'Hors ligne' }}</x-status-badge>
                </div>
                <div class="flex items-center gap-2 rounded-lg border border-border bg-surface p-1 pl-3">
                    <span class="min-w-0 flex-1 truncate font-mono text-xs text-muted-foreground">{{ $publicUrl }}</span>
                    <button type="button" x-data="{ copied: false }" @click="navigator.clipboard.writeText('{{ $publicUrl }}'); copied = true; setTimeout(() => copied = false, 1600)" class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-deep">
                        <span x-text="copied ? 'Copié' : 'Copier'"></span>
                    </button>
                </div>
                <div class="mt-5 flex flex-col items-center rounded-xl border border-border bg-card p-5">
                    <div class="rounded-xl border-4 border-primary-soft p-3">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data={{ urlencode($publicUrl) }}" alt="QR code" class="size-[180px] rounded">
                    </div>
                    <p class="mt-3 text-center text-sm font-semibold">Scannez pour vous inscrire</p>
                    <p class="text-center text-xs text-muted-foreground">À imprimer sur les affiches des campus</p>
                    <a href="https://api.qrserver.com/v1/create-qr-code/?size=500x500&data={{ urlencode($publicUrl) }}" download="qr-recensement-amesco.png" class="mt-4 inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 text-xs font-semibold hover:bg-muted"><x-icon name="download" class="size-3.5" /> Télécharger le QR code</a>
                </div>
            </div>

            <div class="rounded-xl border border-border bg-card p-5 shadow-card">
                <h2 class="text-sm font-semibold mb-4">Répartition des soumissions</h2>
                @foreach ([['Validées', $stats['validated'], 'bg-primary'], ['En attente', $stats['pending'], 'bg-gold'], ['Rejetées', $stats['rejected'], 'bg-destructive'], ['Suspendues', $stats['suspended'], 'bg-muted-foreground']] as [$label, $value, $color])
                    <div class="mb-3 last:mb-0">
                        <div class="mb-1 flex justify-between text-xs"><span class="font-medium">{{ $label }}</span><span class="tabular text-muted-foreground">{{ $value }}</span></div>
                        <div class="h-2 overflow-hidden rounded-full bg-muted"><div class="h-full rounded-full {{ $color }}" style="width: {{ $stats['total'] ? ($value / $stats['total'] * 100) : 0 }}%"></div></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
