<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'AM.E.S.CO E-VOTE' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-background text-foreground min-h-screen flex flex-col">
    <header class="border-b border-border bg-card">
        <div class="mx-auto max-w-5xl px-4 py-4 flex items-center justify-between">
            <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2">
                <img src="{{ asset('images/logo.png') }}" alt="AM.E.S.CO" class="h-10 w-auto">
                <span class="font-extrabold tracking-tight">E-VOTE</span>
            </a>
            <nav class="flex items-center gap-4 text-sm font-medium">
                <a href="{{ route('vote.access') }}" wire:navigate class="hover:text-primary">Accéder à un vote</a>
                @auth
                    <a href="{{ route('admin.dashboard') }}" wire:navigate class="rounded-lg bg-primary px-3 py-1.5 text-white hover:bg-primary-deep">Administration</a>
                @else
                    <a href="{{ route('login') }}" wire:navigate class="rounded-lg border border-border px-3 py-1.5 hover:bg-muted">Connexion admin</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="flex-1">
        {{ $slot }}
    </main>

    <footer class="border-t border-border bg-card">
        <div class="mx-auto max-w-5xl px-4 py-6 text-xs text-muted-foreground flex flex-wrap items-center justify-between gap-2">
            <span>© {{ now()->year }} Amicale des Élèves, Étudiants et Stagiaires Congolais au Sénégal</span>
            <span>La démocratie numérique, au service de notre communauté.</span>
        </div>
    </footer>
</body>
</html>
