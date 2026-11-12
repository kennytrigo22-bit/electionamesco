<?php

use App\Models\Election;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.public')] class extends Component
{
    use WithFileUploads;

    public Election $election;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $institution = '';

    public string $program = '';

    public string $bio = '';

    public $photo = null;

    public bool $submitted = false;

    public function mount(Election $election): void
    {
        abort_if($election->status === 'closed', 404);
        $this->election = $election;
    }

    public function submit(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string'],
            'institution' => ['nullable', 'string'],
            'program' => ['nullable', 'string'],
            'bio' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);

        $photoPath = $this->photo?->store('candidacies', 'public');

        $this->election->candidacies()->create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'institution' => $this->institution,
            'program' => $this->program,
            'bio' => $this->bio,
            'photo_path' => $photoPath,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $this->submitted = true;
    }
}; ?>

<div class="max-w-xl mx-auto py-10 px-4">
    <div class="rounded-2xl border border-border bg-card shadow-card p-6">
        @if ($submitted)
            <div class="text-center py-6">
                <span class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-success-soft text-success"><x-icon name="check" class="size-7" /></span>
                <h1 class="text-xl font-bold mb-2">Candidature envoyée</h1>
                <p class="text-sm text-muted-foreground">Votre candidature pour « {{ $election->title }} » sera examinée par la Commission Électorale Indépendante.</p>
            </div>
        @else
            <h1 class="text-xl font-bold mb-1">Déposer ma candidature</h1>
            <p class="text-sm text-muted-foreground mb-6">{{ $election->title }}</p>

            <form wire:submit="submit" class="space-y-4">
                <div>
                    <label class="text-sm font-medium">Nom complet *</label>
                    <input type="text" wire:model="name" class="mt-1 block w-full rounded-lg border-border text-sm">
                    @error('name') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium">Email *</label>
                        <input type="email" wire:model="email" class="mt-1 block w-full rounded-lg border-border text-sm">
                        @error('email') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium">Téléphone</label>
                        <input type="text" wire:model="phone" class="mt-1 block w-full rounded-lg border-border text-sm">
                    </div>
                </div>
                <div>
                    <label class="text-sm font-medium">Établissement</label>
                    <input type="text" wire:model="institution" class="mt-1 block w-full rounded-lg border-border text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium">Programme / profession de foi</label>
                    <textarea wire:model="program" rows="3" class="mt-1 block w-full rounded-lg border-border text-sm"></textarea>
                </div>
                <div>
                    <label class="text-sm font-medium">Biographie</label>
                    <textarea wire:model="bio" rows="3" class="mt-1 block w-full rounded-lg border-border text-sm"></textarea>
                </div>
                <div>
                    <label class="text-sm font-medium">Photo</label>
                    <input type="file" accept="image/*" wire:model="photo" class="mt-1 block w-full text-sm">
                    @if ($photo) <img src="{{ $photo->temporaryUrl() }}" class="mt-2 size-20 rounded-full object-cover"> @endif
                </div>
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-deep">Soumettre ma candidature</button>
            </form>
        @endif
    </div>
</div>
