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
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @livewireStyles
</head>
<body class="app-body">
    {{-- Page publique : aucune navigation, aucun acces au reste de
         l'application. Seule la marque situe le contexte. --}}
    <header class="app-header">
        <div class="app-header__brand">
            <x-brand-logo variant="light" class="app-header__logo" />
            <span class="app-header__hospital">{{ hospital_name() }}</span>
        </div>
    </header>

    <main class="app-main app-main--narrow">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
