<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<button type="button" wire:click="logout" class="flex w-full items-center gap-2 px-3 py-2 text-sm text-destructive hover:bg-destructive-soft">
    <x-icon name="log-out" class="size-4" /> Se déconnecter
</button>
