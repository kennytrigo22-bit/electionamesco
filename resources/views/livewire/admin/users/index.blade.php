<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin', ['title' => 'Utilisateurs et rôles'])] class extends Component
{
    public bool $showCreate = false;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = 'admin';

    public ?string $fonction = null;

    public array $fonctions = [
        'PR_CEI' => 'Président CEI', 'VP_CEI' => 'Vice-Président CEI', 'R_CEI' => 'Rapporteur CEI',
        'PR_BE' => 'Président Bureau Exécutif', 'S_BE' => 'Secrétaire Bureau Exécutif', 'M_BE' => 'Membre Bureau Exécutif',
    ];

    public array $roleCards = [
        ['key' => 'super_admin', 'title' => 'Super administrateur', 'description' => 'Accès complet, y compris la gestion des comptes et des paramètres système.'],
        ['key' => 'cei', 'title' => 'Commission Électorale Indépendante', 'description' => 'Gère les élections, urnes, candidatures, invitations et résultats (PR_CEI, VP_CEI, R_CEI).'],
        ['key' => 'be', 'title' => 'Bureau Exécutif', 'description' => 'Gère les formulaires, les inscriptions et le fichier électoral (PR_BE, S_BE, M_BE).'],
    ];

    public function create(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:super_admin,admin'],
            'fonction' => ['nullable', 'in:'.implode(',', array_keys($this->fonctions))],
        ]);

        User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'role' => $this->role,
            'fonction' => $this->role === 'admin' ? $this->fonction : null,
        ]);

        $this->reset(['name', 'email', 'password', 'fonction', 'showCreate']);
        $this->role = 'admin';
        session()->flash('status', 'Administrateur créé.');
    }

    public function delete(User $user): void
    {
        abort_if($user->id === auth()->id(), 403);
        $user->delete();
    }

    public function with(): array
    {
        return ['users' => User::latest()->get()];
    }
}; ?>

<div>
    <x-page-header title="Utilisateurs et rôles" description="Gestion des comptes administrateurs de la plateforme.">
        <x-slot:actions>
            <button type="button" wire:click="$toggle('showCreate')" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-deep"><x-icon name="plus" class="size-4" /> Nouvel administrateur</button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-success-soft text-success text-sm px-4 py-2">{{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        @foreach ($roleCards as $card)
            <div class="rounded-xl border border-border bg-card p-4 shadow-card">
                <p class="font-semibold text-sm">{{ $card['title'] }}</p>
                <p class="mt-1 text-xs text-muted-foreground">{{ $card['description'] }}</p>
            </div>
        @endforeach
    </div>

    @if ($showCreate)
        <div class="rounded-xl border border-border bg-card p-5 shadow-card mb-6">
            <h2 class="text-sm font-semibold mb-4">Nouvel administrateur</h2>
            <form wire:submit="create" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Nom</label>
                    <input type="text" wire:model="name" class="mt-1 block w-full rounded-lg border-border text-sm">
                    @error('name') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Email</label>
                    <input type="email" wire:model="email" class="mt-1 block w-full rounded-lg border-border text-sm">
                    @error('email') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Mot de passe</label>
                    <input type="password" wire:model="password" class="mt-1 block w-full rounded-lg border-border text-sm">
                    @error('password') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Rôle</label>
                    <select wire:model.live="role" class="mt-1 block w-full rounded-lg border-border text-sm">
                        <option value="admin">Admin</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
                @if ($role === 'admin')
                    <div class="sm:col-span-2">
                        <label class="text-xs font-medium text-muted-foreground">Fonction</label>
                        <select wire:model="fonction" class="mt-1 block w-full rounded-lg border-border text-sm">
                            <option value="">—</option>
                            @foreach ($fonctions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="sm:col-span-2">
                    <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-deep">Créer</button>
                </div>
            </form>
        </div>
    @endif

    <div class="rounded-xl border border-border bg-card shadow-card divide-y divide-border">
        @foreach ($users as $user)
            <div class="p-4 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <x-person-avatar :name="$user->name" />
                    <div>
                        <p class="font-medium text-sm">{{ $user->name }} <span class="text-xs text-muted-foreground">({{ $user->email }})</span></p>
                        <p class="text-xs text-muted-foreground">{{ $user->role === 'super_admin' ? 'Super Admin' : 'Admin' }} @if($user->fonction) · {{ $fonctions[$user->fonction] ?? $user->fonction }} @endif</p>
                    </div>
                </div>
                @if ($user->id !== auth()->id())
                    <button type="button" wire:click="delete({{ $user->id }})" wire:confirm="Supprimer ce compte ?" class="rounded-lg bg-destructive-soft px-3 py-1.5 text-xs font-semibold text-destructive hover:brightness-95">Supprimer</button>
                @endif
            </div>
        @endforeach
    </div>
</div>
