<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = 'admin';

    public ?string $fonction = null;

    public array $fonctions = [
        'PR_CEI' => 'Président CEI',
        'VP_CEI' => 'Vice-Président CEI',
        'R_CEI' => 'Rapporteur CEI',
        'PR_BE' => 'Président Bureau Exécutif',
        'S_BE' => 'Secrétaire Bureau Exécutif',
        'M_BE' => 'Membre Bureau Exécutif',
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

        $this->reset(['name', 'email', 'password', 'fonction']);
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
        return [
            'users' => User::latest()->get(),
        ];
    }
}; ?>

<div>
    <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8 space-y-8">
        @if (session('status'))
            <div class="bg-green-50 text-green-800 text-sm rounded-md p-3">{{ session('status') }}</div>
        @endif

        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
            <h2 class="text-lg font-semibold mb-4">Nouvel administrateur</h2>
            <form wire:submit="create" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label value="Nom" />
                    <x-text-input wire:model="name" class="block w-full mt-1" />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label value="Email" />
                    <x-text-input type="email" wire:model="email" class="block w-full mt-1" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>
                <div>
                    <x-input-label value="Mot de passe" />
                    <x-text-input type="password" wire:model="password" class="block w-full mt-1" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>
                <div>
                    <x-input-label value="Rôle" />
                    <select wire:model.live="role" class="block w-full mt-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                        <option value="admin">Admin</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
                @if ($role === 'admin')
                    <div class="sm:col-span-2">
                        <x-input-label value="Fonction" />
                        <select wire:model="fonction" class="block w-full mt-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                            <option value="">—</option>
                            @foreach ($fonctions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="sm:col-span-2">
                    <x-primary-button type="submit">Créer</x-primary-button>
                </div>
            </form>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
            <h2 class="text-lg font-semibold mb-4">Administrateurs</h2>
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($users as $user)
                    <div class="py-3 flex items-center justify-between gap-4">
                        <div>
                            <p class="font-medium">{{ $user->name }} <span class="text-sm text-gray-500">({{ $user->email }})</span></p>
                            <p class="text-sm text-gray-500">
                                {{ $user->role === 'super_admin' ? 'Super Admin' : 'Admin' }}
                                @if ($user->fonction) — {{ $fonctions[$user->fonction] ?? $user->fonction }} @endif
                            </p>
                        </div>
                        @if ($user->id !== auth()->id())
                            <x-danger-button wire:click="delete({{ $user->id }})" wire:confirm="Supprimer ce compte ?">Supprimer</x-danger-button>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
