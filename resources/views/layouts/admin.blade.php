<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Tableau de bord' }} — AM.E.S.CO E-VOTE</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-background text-foreground">
@php
    $navGroups = [
        'Pilotage' => [
            ['label' => 'Tableau de bord', 'route' => 'admin.dashboard', 'icon' => 'dashboard'],
        ],
        'Recensement' => [
            ['label' => 'Recensement', 'route' => 'admin.forms.index', 'icon' => 'clipboard', 'perm' => 'be'],
            ['label' => 'Membres', 'route' => 'admin.membres.index', 'icon' => 'users', 'perm' => 'be'],
            ['label' => 'Fichier électoral', 'route' => 'admin.fichier-electoral', 'icon' => 'book-user', 'perm' => 'be'],
        ],
        'Scrutins' => [
            ['label' => 'Élections', 'route' => 'admin.elections.index', 'icon' => 'vote', 'perm' => 'cei'],
            ['label' => 'Urnes et bulletins', 'route' => 'admin.urnes', 'icon' => 'archive', 'perm' => 'cei'],
            ['label' => 'Candidatures', 'route' => 'admin.candidatures.index', 'icon' => 'user-check', 'perm' => 'cei'],
            ['label' => 'Votes', 'route' => 'admin.votes.index', 'icon' => 'activity', 'perm' => 'cei'],
            ['label' => 'Résultats', 'route' => 'admin.resultats.index', 'icon' => 'bar-chart', 'perm' => 'cei'],
        ],
        'Administration' => [
            ['label' => 'Rapports', 'route' => 'admin.rapports.index', 'icon' => 'file-text'],
            ['label' => 'Utilisateurs et rôles', 'route' => 'admin.users.index', 'icon' => 'shield-check', 'perm' => 'super_admin'],
            ['label' => 'Paramètres', 'route' => 'admin.parametres', 'icon' => 'settings'],
        ],
    ];
    $user = auth()->user();
    $hasPerm = fn ($perm) => match ($perm) {
        'be' => $user->can('manage', \App\Models\Form::class),
        'cei' => $user->can('manage', \App\Models\Election::class),
        'super_admin' => $user->isSuperAdmin(),
        default => true,
    };
    $navGroups = collect($navGroups)
        ->map(fn ($items) => collect($items)->filter(fn ($item) => $hasPerm($item['perm'] ?? null))->values()->all())
        ->filter(fn ($items) => count($items) > 0)
        ->all();
    $fonctionLabels = [
        'PR_CEI' => 'Président CEI', 'VP_CEI' => 'Vice-Président CEI', 'R_CEI' => 'Rapporteur CEI',
        'PR_BE' => 'Président Bureau Exécutif', 'S_BE' => 'Secrétaire BE', 'M_BE' => 'Membre BE',
    ];
    $roleLabel = auth()->user()?->isSuperAdmin() ? 'Super administrateur' : ($fonctionLabels[auth()->user()?->fonction] ?? 'Administrateur');
    $activeElection = \App\Models\Election::where('status', 'open')->latest()->first();
    $pendingCount = \App\Models\FormSubmission::where('status', 'pending')->count();
@endphp
<div x-data="{ collapsed: false, mobileOpen: false }" class="flex min-h-screen w-full">
    <aside :class="collapsed ? 'w-[72px]' : 'w-[252px]'" class="sticky top-0 hidden h-screen shrink-0 flex-col bg-sidebar text-sidebar-foreground transition-[width] duration-200 lg:flex">
        <div class="flex h-16 items-center gap-3 border-b border-sidebar-border px-4" :class="collapsed && 'justify-center px-2'">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 overflow-hidden">
                <span class="inline-flex shrink-0 items-center justify-center rounded-lg bg-primary text-white font-extrabold px-2 py-1.5 text-xs">AMS</span>
                <span x-show="!collapsed" class="flex flex-col leading-none">
                    <span class="text-sm font-extrabold tracking-tight">E-VOTE</span>
                    <span class="mt-1 text-[10.5px] text-sidebar-muted">Administration</span>
                </span>
            </a>
        </div>

        <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-4">
            @foreach ($navGroups as $group => $items)
                <div>
                    <div x-show="!collapsed" class="mb-1.5 px-3 text-[10.5px] font-semibold uppercase tracking-[0.12em] text-sidebar-muted/80">{{ $group }}</div>
                    <ul class="space-y-0.5">
                        @foreach ($items as $item)
                            @php $active = request()->routeIs($item['route'].'*'); @endphp
                            <li>
                                <a href="{{ route($item['route']) }}" wire:navigate
                                   class="group relative flex h-9 items-center gap-3 rounded-lg px-3 text-[13.5px] font-medium transition-colors {{ $active ? 'bg-sidebar-accent text-white' : 'text-sidebar-muted hover:bg-sidebar-accent/60 hover:text-white' }}"
                                   :class="collapsed && 'justify-center px-0'">
                                    @if ($active)
                                        <span class="absolute inset-y-2 left-0 w-[3px] rounded-r bg-sidebar-primary"></span>
                                    @endif
                                    <x-icon :name="$item['icon']" class="size-[18px] shrink-0 {{ $active ? 'text-sidebar-primary' : '' }}" />
                                    <span x-show="!collapsed">{{ $item['label'] }}</span>
                                    @if ($item['route'] === 'admin.membres.index' && $pendingCount > 0)
                                        <span x-show="!collapsed" class="ml-auto rounded-full bg-sidebar-primary px-1.5 py-px text-[10.5px] font-bold text-sidebar-accent-foreground">{{ $pendingCount }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        <div class="border-t border-sidebar-border p-3">
            <div x-show="!collapsed" class="mb-2 rounded-lg bg-sidebar-accent/60 p-3">
                <div class="flex items-center gap-2 text-xs font-semibold"><x-icon name="shield-check" class="size-3.5 text-sidebar-primary" /> Vote secret garanti</div>
                <p class="mt-1 text-[11px] leading-relaxed text-sidebar-muted">Identités et bulletins sont stockés séparément.</p>
            </div>
            <button type="button" @click="collapsed = !collapsed" class="flex h-9 w-full items-center justify-center gap-2 rounded-lg text-xs font-medium text-sidebar-muted hover:bg-sidebar-accent/60 hover:text-white">
                <x-icon :name="'chevron-left'" x-show="!collapsed" class="size-4" />
                <x-icon :name="'chevron-right'" x-show="collapsed" class="size-4" />
                <span x-show="!collapsed">Replier</span>
            </button>
        </div>
    </aside>

    <div x-show="mobileOpen" x-cloak class="fixed inset-0 z-40 lg:hidden">
        <div class="absolute inset-0 bg-black/40" @click="mobileOpen = false"></div>
        <div class="absolute inset-y-0 left-0 w-[272px] flex flex-col bg-sidebar text-sidebar-foreground">
            <div class="flex h-16 items-center gap-3 border-b border-sidebar-border px-4">
                <span class="inline-flex shrink-0 items-center justify-center rounded-lg bg-primary text-white font-extrabold px-2 py-1.5 text-xs">AMS</span>
                <span class="text-sm font-extrabold tracking-tight">E-VOTE</span>
            </div>
            <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-4">
                @foreach ($navGroups as $group => $items)
                    <div>
                        <div class="mb-1.5 px-3 text-[10.5px] font-semibold uppercase tracking-[0.12em] text-sidebar-muted/80">{{ $group }}</div>
                        <ul class="space-y-0.5">
                            @foreach ($items as $item)
                                @php $active = request()->routeIs($item['route'].'*'); @endphp
                                <li>
                                    <a href="{{ route($item['route']) }}" wire:navigate class="flex h-9 items-center gap-3 rounded-lg px-3 text-[13.5px] font-medium {{ $active ? 'bg-sidebar-accent text-white' : 'text-sidebar-muted hover:bg-sidebar-accent/60 hover:text-white' }}">
                                        <x-icon :name="$item['icon']" class="size-[18px] shrink-0" />
                                        <span>{{ $item['label'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>
        </div>
    </div>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b bg-card/90 border-border px-4 backdrop-blur md:px-6">
            <button type="button" class="inline-flex size-9 items-center justify-center rounded-lg border border-border lg:hidden" @click="mobileOpen = true">
                <x-icon name="menu" class="size-4" />
            </button>
            <div class="min-w-0">
                <div class="truncate text-[15px] font-semibold">{{ $title ?? 'Tableau de bord' }}</div>
                <div class="hidden text-[11.5px] text-muted-foreground sm:block">AM.E.S.CO · {{ \App\Models\Setting::get('session_label', 'Session 2026 – 2027') }}</div>
            </div>

            <div class="ml-auto flex items-center gap-2 md:gap-3">
                <form action="{{ route('admin.membres.index') }}" method="GET" class="relative hidden md:block">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input type="text" name="q" placeholder="Rechercher un membre…" class="h-9 w-64 rounded-lg border border-border bg-background pl-9 pr-3 text-sm outline-none placeholder:text-muted-foreground focus:border-primary focus:ring-2 focus:ring-primary/20 xl:w-80" />
                </form>

                @if ($activeElection)
                    <a href="{{ route('admin.votes.show', $activeElection) }}" wire:navigate class="hidden items-center gap-2 rounded-full border border-primary/20 bg-primary-soft px-3 py-1.5 text-xs font-semibold text-primary-deep sm:inline-flex">
                        <span class="relative flex size-2">
                            <span class="absolute inset-0 rounded-full bg-primary animate-ping"></span>
                            <span class="relative size-2 rounded-full bg-primary"></span>
                        </span>
                        <span class="hidden xl:inline">Scrutin en cours ·</span> {{ \Illuminate\Support\Str::limit($activeElection->title, 24) }}
                    </a>
                @endif

                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = !open" @click.outside="open = false" class="relative inline-flex size-9 items-center justify-center rounded-lg border border-border bg-card hover:bg-muted" aria-label="Notifications">
                        <x-icon name="bell" class="size-4" />
                        @if ($pendingCount > 0)
                            <span class="absolute right-1.5 top-1.5 size-2 rounded-full bg-destructive ring-2 ring-card"></span>
                        @endif
                    </button>
                    <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 mt-2 w-80 rounded-xl border border-border bg-card shadow-elevated z-40">
                        <div class="flex items-center justify-between border-b border-border px-4 py-3">
                            <span class="text-sm font-semibold">Notifications</span>
                        </div>
                        <ul class="divide-y divide-border text-sm max-h-80 overflow-y-auto">
                            @if ($pendingCount > 0)
                                <li class="flex gap-3 px-4 py-3">
                                    <span class="mt-1 size-2 shrink-0 rounded-full bg-gold"></span>
                                    <div><p class="font-medium">{{ $pendingCount }} inscription(s) en attente de validation</p><p class="text-xs text-muted-foreground">Membres</p></div>
                                </li>
                            @endif
                            @if ($activeElection)
                                <li class="flex gap-3 px-4 py-3">
                                    <span class="mt-1 size-2 shrink-0 rounded-full bg-primary"></span>
                                    <div><p class="font-medium">Scrutin « {{ $activeElection->title }} » en cours</p><p class="text-xs text-muted-foreground">Élections</p></div>
                                </li>
                            @endif
                            @if ($pendingCount === 0 && ! $activeElection)
                                <li class="px-4 py-6 text-center text-muted-foreground">Aucune notification</li>
                            @endif
                        </ul>
                    </div>
                </div>

                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = !open" @click.outside="open = false" class="flex items-center gap-2.5 rounded-lg py-1 pl-1 pr-2 hover:bg-muted">
                        <x-person-avatar :name="auth()->user()->name" class="size-8" />
                        <span class="hidden text-left leading-tight md:block">
                            <span class="block text-[13px] font-semibold">{{ auth()->user()->name }}</span>
                            <span class="block text-[11px] text-muted-foreground">{{ $roleLabel }}</span>
                        </span>
                        <x-icon name="chevron-down" class="hidden size-3.5 text-muted-foreground md:block" />
                    </button>
                    <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 mt-2 w-56 rounded-xl border border-border bg-card shadow-elevated z-40 py-1">
                        <div class="px-3 py-2">
                            <div class="text-sm font-semibold">{{ auth()->user()->name }}</div>
                            <div class="text-xs text-muted-foreground">{{ auth()->user()->email }}</div>
                        </div>
                        <div class="border-t border-border my-1"></div>
                        <a href="{{ route('profile') }}" wire:navigate class="flex items-center gap-2 px-3 py-2 text-sm hover:bg-muted"><x-icon name="user-cog" class="size-4" /> Mon compte</a>
                        <a href="{{ route('admin.parametres') }}" wire:navigate class="flex items-center gap-2 px-3 py-2 text-sm hover:bg-muted"><x-icon name="settings" class="size-4" /> Paramètres</a>
                        <div class="border-t border-border my-1"></div>
                        <livewire:layout.logout-link />
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-[1440px] flex-1 px-4 py-6 md:px-8 md:py-8">
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
