<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Un dossier médical ne doit pas être mis en cache par un proxy. --}}
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Dossier médical') · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ \Keneya\Dme\Dme::asset('assets/logo_kdme.png') }}">
    {{-- Feuilles de style et scripts du module, publiés dans le
         répertoire public de l'hôte (vendor:publish --tag=dme-assets). --}}
    {{-- Le thème, relu avant le premier rendu.

         Une page peinte en clair puis basculée en sombre donne un éclair blanc
         à chaque navigation. Ce script est donc volontairement en ligne et
         bloquant, avant la feuille de style.

         La clé est celle de l'application hôte : un praticien qui règle son
         poste dans WorkFlow retrouve le même thème en entrant dans le dossier
         médical, sans avoir à le régler deux fois. --}}
    <script>
        (function () {
            try {
                var choix = localStorage.getItem('keneya.theme');
                if (! choix) {
                    choix = window.matchMedia('(prefers-color-scheme: dark)').matches
                        ? 'sombre'
                        : 'clair';
                }
                document.documentElement.dataset.theme = choix;
            } catch (e) {
                // Stockage refusé : la page reste dans le thème clair.
            }
        })();
    </script>

    <link rel="stylesheet" href="{{ \Keneya\Dme\Dme::asset('build/app.css') }}">
    <script src="{{ \Keneya\Dme\Dme::asset('build/app.js') }}" defer></script>
</head>
<body class="h-full">
<div class="min-h-full lg:flex" x-data="{ sidebarOpen: false }">

    {{-- Navigation latérale (§9), tiroir coulissant sous 1024px (§8) --}}
    <div x-show="sidebarOpen" x-cloak
         class="fixed inset-0 z-30 bg-ink-900/50 lg:hidden"
         @click="sidebarOpen = false" aria-hidden="true"></div>

    <aside class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col border-r border-ink-200 bg-surface
                  transition-transform lg:static lg:translate-x-0"
           :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
           x-cloak
           aria-label="Navigation principale">
        @include('dme::partials.sidebar')
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        @include('dme::partials.topbar')

        <main class="flex-1 px-4 py-5 sm:px-6 lg:px-8" id="contenu-principal">
            @include('dme::partials.flash')
            @yield('content')
        </main>

        <footer class="k-no-print border-t border-ink-200 px-4 py-4 text-xs text-ink-500 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span>{{ \Keneya\Dme\Dme::facility()['name'] }} - Keneya-DME v{{ config('dme.version') }}</span>
                {{-- La mention de démonstration reste tant que l'exploitant ne
                     l'a pas levée (`dme.documents.demo_notice`) : un jeu
                     d'essai pris pour un vrai dossier serait plus grave que
                     l'inverse. Un établissement en exploitation la retire. --}}
                @if (config('dme.documents.demo_notice', true))
                    <span>Données de démonstration fictives. Aucune donnée médicale réelle.</span>
                @endif
            </div>
        </footer>
    </div>
</div>
</body>
</html>
