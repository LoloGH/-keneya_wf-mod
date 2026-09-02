<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('keneya.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @livewireStyles
</head>
<body class="app-body">
    {{-- Barre de marque, identique sur les trois interfaces. Volontairement
         depourvue de tout lien de navigation vers un autre espace : un role,
         une interface. --}}
    <header class="app-header">
        <div class="app-header__brand">
            {{-- Variante couleur : la barre est blanche depuis la refonte. Le
                 nom de l'etablissement domine, celui du produit se lit dessous
                 en second — c'est l'hopital que l'agent doit reconnaitre, pas
                 le logiciel. --}}
            <x-brand-logo variant="color" class="app-header__logo" />
            <span class="app-header__identite">
                <span class="app-header__hospital">{{ $hospitalName ?? hospital_name() }}</span>
                <span class="app-header__produit">{{ config('keneya.name') }}</span>
            </span>
        </div>

        <div class="app-header__context">
            @auth
                {{-- Le nom, puis le rôle — ou le service pour un medecin : les
                     pages surchargent `context` quand il y a plus precis a
                     dire. Les deux lignes se rangent a droite, contre
                     l'avatar : c'est un bloc d'identite, pas deux etiquettes. --}}
                <span class="app-header__qui">
                    <span class="app-header__nom">{{ auth()->user()->name }}</span>
                    <span class="app-header__role">
                        {{ $context ?? auth()->user()->roleLabel() }}
                    </span>
                </span>

                @livewire('shared.notification-bell', [], key('notification-bell'))

                {{-- La deconnexion n'est plus une icone isolee : elle vit
                     dans la carte de profil, a cote du changement de mot de
                     passe (v3.2.3, point 3). --}}
                @livewire('shared.profile-card', [], key('profile-card'))
            @endauth
        </div>
    </header>

    @if (session('error'))
        <div class="alert alert--error" role="alert">{{ session('error') }}</div>
    @endif

    <main class="app-main">
        {{ $slot }}
    </main>

    <x-app-footer />

    @livewireScripts
</body>
</html>
