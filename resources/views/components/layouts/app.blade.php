<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('keneya.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @livewireStyles
</head>
<body class="app-body">
    {{-- Barre de marque, presente sur les trois interfaces. Volontairement
         depourvue de tout lien de navigation : un role, une interface. --}}
    <header class="app-header">
        <div class="app-header__brand">
            <x-brand-logo class="app-header__logo" />
            <span class="app-header__product">{{ config('keneya.name') }}</span>
        </div>

        <div class="app-header__context">
            <span class="app-header__space">{{ $space ?? '' }}</span>

            @auth
                <span class="app-header__user">
                    {{ auth()->user()->name }}
                    <em>{{ \App\Support\Roles::label(auth()->user()->scopedRole()) }}</em>
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn--ghost">Se deconnecter</button>
                </form>
            @endauth

            <span class="app-header__hospital">{{ $hospitalName ?? hospital_name() }}</span>
        </div>
    </header>

    @if (session('error'))
        <div class="alert alert--error" role="alert">{{ session('error') }}</div>
    @endif

    <main class="app-main">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
