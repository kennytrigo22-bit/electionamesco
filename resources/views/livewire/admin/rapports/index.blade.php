<?php

use App\Models\ActivityLog;
use App\Support\ExcelExport;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin', ['title' => 'Rapports'])] class extends Component
{
    use WithPagination;

    public string $actionFilter = 'all';

    public function exportExcel()
    {
        $logs = ActivityLog::with('actor')->latest('created_at')->get();

        return ExcelExport::download(
            $logs->map(fn ($log) => [
                'Date' => $log->created_at->format('d/m/Y H:i'),
                'Action' => $log->action,
                'Description' => $log->description,
                'Auteur' => $log->actor?->name ?? 'Système',
            ])->all(),
            'rapports-activite-amesco'
        );
    }

    public function with(): array
    {
        $query = ActivityLog::with('actor')->latest('created_at');

        if ($this->actionFilter !== 'all') {
            $query->where('action', 'like', $this->actionFilter.'.%');
        }

        return [
            'logs' => $query->paginate(30),
            'actions' => ['all' => 'Toutes les catégories', 'form' => 'Formulaires', 'submission' => 'Inscriptions', 'member' => 'Membres', 'eligibility' => 'Éligibilité', 'register' => 'Fichier électoral', 'election' => 'Élections', 'invitation' => 'Invitations', 'invitations' => 'Invitations (envoi)', 'candidacy' => 'Candidatures', 'results' => 'Résultats'],
        ];
    }
}; ?>

<div>
    <x-page-header title="Rapports" description="Journal d'audit des actions administratives — jamais les bulletins de vote individuels.">
        <x-slot:actions>
            <button type="button" wire:click="exportExcel" class="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-muted"><x-icon name="download" class="size-4" /> Exporter Excel</button>
        </x-slot:actions>
    </x-page-header>

    <select wire:model.live="actionFilter" class="rounded-lg border-border text-sm mb-4">
        @foreach ($actions as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
    </select>

    <div class="rounded-xl border border-border bg-card shadow-card divide-y divide-border">
        @forelse ($logs as $log)
            <div class="p-4 flex items-start gap-3">
                <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary"></span>
                <div class="flex-1">
                    <p class="text-sm">{{ $log->description }}</p>
                    <p class="text-xs text-muted-foreground">{{ $log->created_at->format('d/m/Y H:i') }} · {{ $log->actor?->name ?? 'Système' }}</p>
                </div>
            </div>
        @empty
            <x-empty-state icon="file-text" title="Aucune activité enregistrée" />
        @endforelse
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</div>
