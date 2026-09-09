<!DOCTYPE html>
<html lang="fr" class="h-full" data-theme="clair">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('keneya.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    {{-- Le fond est la premiere chose que voit l'utilisateur : on demande au
         navigateur de le chercher des la lecture de l'en-tete, sans attendre
         d'avoir analyse la feuille de style. --}}
    <link rel="preload" as="image" href="{{ asset('images/login-fond.jpg') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @livewireStyles
</head>
<body class="login-page">
    {{-- La scene porte l'image de la maquette et sert de repere : la carte de
         connexion est positionnee en pourcentages de cet element, ce qui la
         fait tomber exactement sur celle qui est dessinee dans l'image. --}}
    <div class="login-stage">
        <div class="login-stage__scene">
            {{ $slot }}
        </div>
    </div>
    @livewireScripts
</body>
</html>
