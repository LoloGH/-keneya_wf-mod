<!DOCTYPE html>
{{-- L'ecran d'authentification reste en clair : c'est la vitrine du produit,
     et sa scene est une photographie claire qu'un theme sombre eteindrait. --}}
<html lang="fr" class="h-full" data-theme="clair">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('keneya.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    {{-- La scene est la premiere chose que voit l'utilisateur : on demande au
         navigateur de la chercher des la lecture de l'en-tete, sans attendre
         d'avoir analyse la feuille de style. --}}
    <link rel="preload" as="image" href="{{ asset('images/login-scene.jpg') }}"
          media="(min-width: 64rem)">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @livewireStyles
</head>
<body class="login-page">
    {{-- Une seule scene, qui porte la photographie et distribue les quatre
         blocs de la page : la marque et l'accroche, le perimetre, la mention
         d'acces, et la carte de connexion. Rien n'y est voile ni floute : le
         texte se pose la ou la photographie est deja claire. --}}
    <div class="login-scene">
        {{ $slot }}
    </div>
    @livewireScripts
</body>
</html>
