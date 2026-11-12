<?php

use App\Models\ActivityLog;
use App\Models\Election;
use App\Models\FormSubmission;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin', ['title' => 'Tableau de bord'])] class extends Component
{
    public function with(): array
    {
        $totalMembers = FormSubmission::count();
        $validated = FormSubmission::where('status', 'validated')->count();
        $pending = FormSubmission::where('status', 'pending')->count();
        $eligible = FormSubmission::where('eligibility', 'eligible')->count();
        $activeElections = Election::where('status', 'open')->count();
        $activeElection = Election::where('status', 'open')->latest()->first();

        $totalVotes = $activeElection ? $activeElection->voters()->whereNotNull('used_at')->count() : 0;
        $totalVoters = $activeElection ? $activeElection->voters()->count() : 0;
        $participation = $totalVoters > 0 ? round($totalVotes / $totalVoters * 100, 1) : 0;

        $weeks = collect(range(7, 0))->map(function ($weeksAgo) {
            $start = Carbon::now()->subWeeks($weeksAgo)->startOfWeek();
            $end = (clone $start)->endOfWeek();

            return [
                'label' => 'S'.$start->isoWeek(),
                'total' => FormSubmission::whereBetween('submitted_at', [$start, $end])->count(),
                'validated' => FormSubmission::whereBetween('submitted_at', [$start, $end])->where('status', 'validated')->count(),
            ];
        });
        $maxWeek = max(1, $weeks->max('total'));

        $activities = ActivityLog::with('actor')->latest('created_at')->limit(6)->get();

        return [
            'title' => 'Tableau de bord',
            'stats' => [
                'total' => $totalMembers,
                'validated' => $validated,
                'pending' => $pending,
                'eligible' => $eligible,
                'activeElections' => $activeElections,
                'participation' => $participation,
            ],
            'activeElection' => $activeElection,
            'weeks' => $weeks,
            'maxWeek' => $maxWeek,
            'activities' => $activities,
        ];
    }
}; ?>

<div class="space-y-6">
    <div class="rounded-2xl border border-border bg-gradient-to-br from-primary-deep to-primary p-6 text-white shadow-elevated">
        <p class="text-xs font-semibold uppercase tracking-wide text-white/70">{{ now()->translatedFormat('l j F Y') }}</p>
        <h1 class="mt-1 text-2xl font-extrabold">Bonjour, {{ explode(' ', auth()->user()->name)[0] }} 👋</h1>
        <p class="mt-2 max-w-xl text-sm text-white/85">Voici l'état du recensement et du scrutin en cours. Les données sont actualisées en temps réel.</p>
        <div class="mt-4 flex flex-wrap gap-3">
            @can('manage', App\Models\Form::class)
                <a href="{{ route('admin.fichier-electoral') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-white/15 px-4 py-2 text-sm font-semibold backdrop-blur hover:bg-white/25">
                    <x-icon name="book-user" class="size-4" /> Fichier électoral
                </a>
            @endcan
            @can('manage', App\Models\Election::class)
                <a href="{{ route('admin.elections.nouvelle') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2 text-sm font-semibold text-gold-foreground hover:brightness-95">
                    <x-icon name="plus" class="size-4" /> Créer une élection
                </a>
            @endcan
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
        <x-stat-card label="Membres recensés" :value="$stats['total']" icon="users" tone="primary" />
        <x-stat-card label="Électeurs validés" :value="$stats['validated']" icon="user-check" tone="primary" />
        <x-stat-card label="En attente" :value="$stats['pending']" icon="clipboard" tone="gold" />
        <x-stat-card label="Électeurs éligibles" :value="$stats['eligible']" icon="book-user" tone="primary" />
        <x-stat-card label="Élections en cours" :value="$stats['activeElections']" icon="vote" tone="destructive" />
        <x-stat-card label="Taux de participation" :value="$stats['participation'].' %'" icon="activity" tone="gold" />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-xl border border-border bg-card p-5 shadow-card">
            <h2 class="text-sm font-semibold">Évolution des inscriptions</h2>
            <p class="text-xs text-muted-foreground">Inscriptions par semaine (8 dernières semaines)</p>
            <div class="mt-4 flex h-40 items-end gap-3">
                @foreach ($weeks as $week)
                    <div class="flex flex-1 flex-col items-center gap-1">
                        <div class="relative flex h-32 w-full items-end justify-center gap-0.5">
                            <div class="w-2.5 rounded-t bg-muted" style="height: {{ max(4, $week['total'] / $maxWeek * 100) }}%"></div>
                            <div class="w-2.5 rounded-t bg-primary" style="height: {{ max(4, $week['validated'] / $maxWeek * 100) }}%"></div>
                        </div>
                        <span class="text-[10px] text-muted-foreground">{{ $week['label'] }}</span>
                    </div>
                @endforeach
            </div>
            <div class="mt-3 flex items-center gap-4 text-xs text-muted-foreground">
                <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-muted-foreground/40"></span> Inscrits</span>
                <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-primary"></span> Validés</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-5 shadow-card">
            <h2 class="text-sm font-semibold mb-3">Actions rapides</h2>
            <div class="space-y-2">
                @can('manage', App\Models\Form::class)
                    <a href="{{ route('admin.forms.index') }}" wire:navigate class="flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm font-medium hover:bg-muted"><x-icon name="clipboard" class="size-4 text-primary" /> Créer un formulaire</a>
                    <a href="{{ route('admin.membres.index') }}" wire:navigate class="flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm font-medium hover:bg-muted"><x-icon name="users" class="size-4 text-primary" /> Ajouter un membre</a>
                    <a href="{{ route('admin.fichier-electoral') }}" wire:navigate class="flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm font-medium hover:bg-muted"><x-icon name="book-user" class="size-4 text-primary" /> Fichier électoral</a>
                @endcan
                @can('manage', App\Models\Election::class)
                    <a href="{{ route('admin.elections.nouvelle') }}" wire:navigate class="flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm font-medium hover:bg-muted"><x-icon name="vote" class="size-4 text-primary" /> Créer une élection</a>
                @endcan
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-xl border border-border bg-card p-5 shadow-card">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold">Élection active</h2>
                @if ($activeElection)
                    <a href="{{ route('admin.votes.show', $activeElection) }}" wire:navigate class="text-xs font-semibold text-primary">Suivi détaillé</a>
                @endif
            </div>
            @if ($activeElection)
                <div class="rounded-lg border border-border p-4">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold">{{ $activeElection->title }}</span>
                        <x-status-badge tone="success">Ouverte</x-status-badge>
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">{{ $activeElection->voters()->count() }} électeurs inscrits · {{ $activeElection->voters()->whereNotNull('used_at')->count() }} votes enregistrés</p>
                    <div class="mt-3 h-2 w-full rounded-full bg-muted">
                        <div class="h-2 rounded-full bg-primary" style="width: {{ $stats['participation'] }}%"></div>
                    </div>
                </div>
            @else
                <x-empty-state icon="vote" title="Aucune élection en cours" description="Créez une élection pour commencer un scrutin." />
            @endif
        </div>

        <div class="rounded-xl border border-border bg-card p-5 shadow-card">
            <h2 class="text-sm font-semibold mb-3">Activité récente</h2>
            <ul class="space-y-3 text-sm">
                @forelse ($activities as $activity)
                    <li class="flex gap-2">
                        <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary"></span>
                        <div>
                            <p>{{ $activity->description }}</p>
                            <p class="text-xs text-muted-foreground">{{ $activity->created_at->diffForHumans() }} @if($activity->actor) · {{ $activity->actor->name }} @endif</p>
                        </div>
                    </li>
                @empty
                    <li class="text-xs text-muted-foreground">Aucune activité récente.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
