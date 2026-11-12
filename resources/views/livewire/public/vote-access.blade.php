<?php

use App\Models\ElectionVoter;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.public')] class extends Component
{
    public string $tokenInput = '';

    public string $error = '';

    public function go(): void
    {
        $value = trim($this->tokenInput);
        $token = str_contains($value, '/vote/') ? trim(strrchr($value, '/'), '/') : $value;

        if (! ElectionVoter::where('token', $token)->exists()) {
            $this->error = "Lien ou code de vote introuvable. Vérifiez l'email reçu de la Commission Électorale.";

            return;
        }

        $this->redirect(route('vote.show', ['token' => $token]), navigate: true);
    }
}; ?>

<div class="max-w-md mx-auto py-16 px-4">
    <div class="rounded-2xl border border-border bg-card shadow-card p-8 text-center">
        <span class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-primary-soft text-primary-deep"><x-icon name="vote" class="size-7" /></span>
        <h1 class="text-xl font-bold mb-2">Accéder à un vote</h1>
        <p class="text-sm text-muted-foreground mb-6">Collez votre lien de vote personnel reçu par email.</p>

        @if ($error)
            <div class="mb-4 rounded-lg bg-destructive-soft text-destructive text-sm px-3 py-2 text-left">{{ $error }}</div>
        @endif

        <form wire:submit="go" class="space-y-3">
            <input type="text" wire:model="tokenInput" placeholder="https://.../vote/xxxxxxxx" class="block w-full rounded-lg border-border text-sm">
            <button type="submit" class="w-full rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-deep">Continuer</button>
        </form>
    </div>
</div>
