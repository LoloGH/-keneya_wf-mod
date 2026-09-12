<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? config('keneya.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    {{-- Le theme, relu avant le premier rendu.

         Une page peinte en clair puis basculee en sombre donne un eclair blanc
         a chaque navigation : sur un poste de garde, la nuit, c'est
         desagreable au point qu'on renonce au mode sombre. Ce script est donc
         volontairement en ligne et bloquant, avant la feuille de style.

         Sans choix enregistre, on suit le systeme. --}}
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
                // Stockage refuse : la page reste dans le theme clair.
            }
        })();
    </script>

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @livewireStyles
</head>
<body class="app-body">
    {{-- Page publique : aucune navigation, aucun acces au reste de
         l'application. Seule la marque situe le contexte. --}}
    <header class="app-header">
        <div class="app-header__brand">
            {{-- Variante couleur : la barre est blanche depuis la refonte, la
                 declinaison claire y perdait tout contraste. --}}
            <x-brand-logo variant="color" class="app-header__logo" />
            <span class="app-header__identite">
                <span class="app-header__hospital">{{ hospital_name() }}</span>
                <span class="app-header__produit">{{ $subtitle ?? 'Espace patient' }}</span>
            </span>
        </div>
    </header>

    <main class="app-main app-main--narrow">
        {{ $slot }}
    </main>

    <x-app-footer />

    @livewireScripts
</body>
</html>
