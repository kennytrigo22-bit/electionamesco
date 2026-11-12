<?php

use App\Models\ActivityLog;
use App\Models\FormSubmission;
use Illuminate\Support\Facades\Response;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin', ['title' => 'Membres'])] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public function mount(): void
    {
        $this->search = (string) request()->query('q', '');
    }

    protected function setStatus(FormSubmission $submission, string $status): void
    {
        $submission->update(['status' => $status, 'validated_by' => auth()->id(), 'validated_at' => now()]);
        ActivityLog::record('member.'.$status, "Membre « {$submission->displayName()} » : ".$submission->statusLabel(), $submission);
    }

    public function validateMember(FormSubmission $submission): void
    {
        $this->setStatus($submission, 'validated');
    }

    public function rejectMember(FormSubmission $submission): void
    {
        $this->setStatus($submission, 'rejected');
    }

    public function suspendMember(FormSubmission $submission): void
    {
        $this->setStatus($submission, 'suspended');
    }

    public function exportCsv()
    {
        $members = FormSubmission::with('values.field', 'form')->get();

        return Response::streamDownload(function () use ($members) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Nom', 'Formulaire', 'Statut', 'Éligibilité', 'Date inscription']);
            foreach ($members as $m) {
                fputcsv($out, [$m->displayName(), $m->form->title, $m->statusLabel(), $m->eligibilityLabel(), $m->submitted_at->format('d/m/Y')]);
            }
            fclose($out);
        }, 'membres.csv');
    }

    public function with(): array
    {
        $query = FormSubmission::with(['values.field', 'form'])->latest('submitted_at');

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $members = $query->paginate(25);

        if ($this->search !== '') {
            $members->setCollection(
                $members->getCollection()->filter(fn ($m) => str_contains(mb_strtolower($m->displayName()), mb_strtolower($this->search)))
            );
        }

        return [
            'members' => $members,
            'stats' => [
                'total' => FormSubmission::count(),
                'validated' => FormSubmission::where('status', 'validated')->count(),
                'pending' => FormSubmission::where('status', 'pending')->count(),
                'rejected' => FormSubmission::where('status', 'rejected')->count(),
            ],
        ];
    }
}; ?>

<div>
    <x-page-header title="Membres" description="Annuaire de toutes les inscriptions, tous formulaires confondus.">
        <x-slot:actions>
            <button type="button" wire:click="exportCsv" class="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-muted"><x-icon name="download" class="size-4" /> Exporter CSV</button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-6">
        <x-stat-card label="Total" :value="$stats['total']" icon="users" tone="muted" />
        <x-stat-card label="Validés" :value="$stats['validated']" icon="user-check" tone="primary" />
        <x-stat-card label="En attente" :value="$stats['pending']" icon="clipboard" tone="gold" />
        <x-stat-card label="Rejetés" :value="$stats['rejected']" icon="x" tone="destructive" />
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Rechercher un membre..." class="rounded-lg border-border text-sm max-w-xs">
        <select wire:model.live="statusFilter" class="rounded-lg border-border text-sm">
            <option value="all">Tous les statuts</option>
            <option value="pending">En attente</option>
            <option value="validated">Validés</option>
            <option value="rejected">Rejetés</option>
            <option value="suspended">Suspendus</option>
        </select>
    </div>

    <div class="rounded-xl border border-border bg-card shadow-card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                <tr>
                    <th class="px-4 py-3 text-left">Membre</th>
                    <th class="px-4 py-3 text-left">Formulaire</th>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Statut</th>
                    <th class="px-4 py-3 text-left">Éligibilité</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($members as $member)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <x-person-avatar :name="$member->displayName()" class="size-7" />
                                <span class="font-medium">{{ $member->displayName() }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">{{ $member->form->title }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ $member->submitted_at->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">
                            <x-status-badge :tone="match($member->status) { 'validated' => 'success', 'pending' => 'gold', 'rejected' => 'destructive', 'suspended' => 'muted' }">{{ $member->statusLabel() }}</x-status-badge>
                        </td>
                        <td class="px-4 py-3">
                            @if ($member->eligibility)
                                <x-status-badge :tone="match($member->eligibility) { 'eligible' => 'success', 'non_eligible' => 'destructive', 'verification' => 'gold' }">{{ $member->eligibilityLabel() }}</x-status-badge>
                            @else
                                <span class="text-xs text-muted-foreground">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-1.5">
                                @if ($member->status !== 'validated')
                                    <button type="button" wire:click="validateMember({{ $member->id }})" class="rounded-lg p-1.5 text-primary hover:bg-primary-soft" title="Valider"><x-icon name="check" class="size-4" /></button>
                                @endif
                                @if ($member->status !== 'rejected')
                                    <button type="button" wire:click="rejectMember({{ $member->id }})" class="rounded-lg p-1.5 text-destructive hover:bg-destructive-soft" title="Rejeter"><x-icon name="x" class="size-4" /></button>
                                @endif
                                @if ($member->status !== 'suspended')
                                    <button type="button" wire:click="suspendMember({{ $member->id }})" wire:confirm="Suspendre ce membre ?" class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted" title="Suspendre"><x-icon name="lock" class="size-4" /></button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty-state icon="users" title="Aucun membre" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $members->links() }}</div>
</div>
