<?php

use App\Models\ActivityLog;
use App\Models\FormSubmission;
use App\Models\Setting;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin', ['title' => 'Fichier électoral'])] class extends Component
{
    use WithPagination;

    public string $search = '';

    public function setEligibility(FormSubmission $submission, string $eligibility): void
    {
        abort_unless($submission->status === 'validated', 403);
        $submission->update(['eligibility' => $eligibility]);
        ActivityLog::record('eligibility.updated', "Éligibilité de « {$submission->displayName()} » : ".$submission->eligibilityLabel(), $submission);
    }

    public function lockRegister(): void
    {
        Setting::set('electoral_register_locked_at', now()->toDateTimeString());
        ActivityLog::record('register.locked', 'Fichier électoral verrouillé');
    }

    public function unlockRegister(): void
    {
        Setting::set('electoral_register_locked_at', null);
        ActivityLog::record('register.unlocked', 'Fichier électoral déverrouillé');
    }

    public function with(): array
    {
        $base = FormSubmission::where('status', 'validated')->with(['values.field', 'form']);

        $query = (clone $base)->latest('submitted_at');

        if ($this->search !== '') {
            $query->whereHas('values', fn ($q) => $q->where('value', 'like', '%'.$this->search.'%'));
        }

        $voters = $query->paginate(25);

        $duplicates = (clone $base)->get()->countBy(fn ($m) => $m->displayName())->filter(fn ($count) => $count > 1)->count();

        return [
            'voters' => $voters,
            'lockedAt' => Setting::get('electoral_register_locked_at'),
            'stats' => [
                'total' => (clone $base)->count(),
                'eligible' => (clone $base)->where('eligibility', 'eligible')->count(),
                'nonEligible' => (clone $base)->where('eligibility', 'non_eligible')->count(),
                'verification' => (clone $base)->where(fn ($q) => $q->whereNull('eligibility')->orWhere('eligibility', 'verification'))->count(),
                'duplicates' => $duplicates,
            ],
        ];
    }
}; ?>

<div>
    <x-page-header title="Fichier électoral" description="Registre consolidé des membres validés, base de toutes les listes électorales.">
        <x-slot:actions>
            @if ($lockedAt)
                <button type="button" wire:click="unlockRegister" class="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-muted"><x-icon name="lock" class="size-4" /> Déverrouiller</button>
            @else
                <button type="button" wire:click="lockRegister" wire:confirm="Verrouiller le fichier électoral avant une élection ?" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-deep"><x-icon name="lock" class="size-4" /> Verrouiller</button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($lockedAt)
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-gold-soft text-gold-foreground text-sm px-4 py-2">
            <x-icon name="lock" class="size-4" /> Fichier électoral verrouillé depuis le {{ \Illuminate\Support\Carbon::parse($lockedAt)->format('d/m/Y H:i') }}
        </div>
    @endif

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-5 mb-6">
        <x-stat-card label="Membres validés" :value="$stats['total']" icon="users" tone="muted" />
        <x-stat-card label="Électeurs éligibles" :value="$stats['eligible']" icon="book-user" tone="primary" />
        <x-stat-card label="Non éligibles" :value="$stats['nonEligible']" icon="x" tone="destructive" />
        <x-stat-card label="À vérifier" :value="$stats['verification']" icon="eye" tone="gold" />
        <x-stat-card label="Doublons potentiels" :value="$stats['duplicates']" icon="sparkles" tone="destructive" />
    </div>

    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Rechercher..." class="rounded-lg border-border text-sm max-w-xs mb-4">

    <div class="rounded-xl border border-border bg-card shadow-card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                <tr>
                    <th class="px-4 py-3 text-left">Électeur</th>
                    <th class="px-4 py-3 text-left">Formulaire source</th>
                    <th class="px-4 py-3 text-left">Éligibilité</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($voters as $voter)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <x-person-avatar :name="$voter->displayName()" class="size-7" />
                                <span class="font-medium">{{ $voter->displayName() }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">{{ $voter->form->title }}</td>
                        <td class="px-4 py-3">
                            <x-status-badge :tone="match($voter->eligibility) { 'eligible' => 'success', 'non_eligible' => 'destructive', default => 'gold' }">{{ $voter->eligibilityLabel() }}</x-status-badge>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex gap-1.5">
                                <button type="button" wire:click="setEligibility({{ $voter->id }}, 'eligible')" class="rounded-lg px-2 py-1 text-xs font-semibold text-primary hover:bg-primary-soft">Éligible</button>
                                <button type="button" wire:click="setEligibility({{ $voter->id }}, 'non_eligible')" class="rounded-lg px-2 py-1 text-xs font-semibold text-destructive hover:bg-destructive-soft">Non éligible</button>
                                <button type="button" wire:click="setEligibility({{ $voter->id }}, 'verification')" class="rounded-lg px-2 py-1 text-xs font-semibold text-gold-foreground hover:bg-gold-soft">À vérifier</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-empty-state icon="book-user" title="Aucun électeur validé" description="Validez des inscriptions dans Membres pour alimenter le fichier électoral." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $voters->links() }}</div>
</div>
