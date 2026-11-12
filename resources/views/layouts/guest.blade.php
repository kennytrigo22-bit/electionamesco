<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <link rel="icon" href="{{ asset('images/logo.png') }}">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Connexion administrateur — AM.E.S.CO E-VOTE</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-foreground antialiased">
        <div class="grid min-h-screen lg:grid-cols-2">
            <div class="relative hidden flex-col justify-between overflow-hidden bg-sidebar p-12 text-sidebar-foreground lg:flex">
                <div class="absolute inset-0 opacity-[0.07]" style="background-image: radial-gradient(circle at 1px 1px, #fff 1px, transparent 0); background-size: 22px 22px;"></div>

                <span class="relative inline-flex w-fit rounded-xl bg-card p-2 shadow-card">
                    <img src="{{ asset('images/logo.png') }}" alt="AM.E.S.CO" class="h-14 w-auto select-none">
                </span>

                <div class="relative">
                    <div class="mb-6 h-1 w-24 rounded-full" style="background: linear-gradient(90deg, #218A45 0 40%, #F5C928 40% 70%, #D92732 70% 100%);"></div>
                    <h1 class="text-4xl font-bold leading-tight">La démocratie numérique,<br>au service de notre communauté.</h1>
                    <p class="mt-4 max-w-md text-sidebar-muted">Recensement, fichier électoral et scrutins sécurisés de l'Amicale des Élèves, Étudiants et Stagiaires Congolais au Sénégal.</p>
                </div>

                <p class="relative text-xs text-sidebar-muted">© {{ now()->year }} AM.E.S.CO · Dakar, Sénégal</p>
            </div>

            <div class="flex items-center justify-center p-6 bg-background">
                <div class="w-full max-w-sm">
                    <img src="{{ asset('images/logo.png') }}" alt="AM.E.S.CO" class="mb-8 h-14 w-auto select-none lg:hidden">

                    {{ $slot }}

                    <a href="{{ route('home') }}" wire:navigate class="mt-6 block text-center text-sm text-muted-foreground hover:text-foreground">&larr; Retour au site public</a>
                </div>
            </div>
        </div>
    </body>
</html>
