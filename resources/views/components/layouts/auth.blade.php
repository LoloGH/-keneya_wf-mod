<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('keneya.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    {{-- Visuel institutionnel remplacable sans toucher au code : il suffit de
         deposer une autre image sous public/images/login-background.jpg. --}}
    <style>
        .login-screen {
            background-image:
                linear-gradient(rgba(10, 63, 97, .82), rgba(10, 63, 97, .82)),
                url('{{ asset('images/login-background.jpg') }}');
        }
    </style>
    @livewireStyles
</head>
<body class="login-screen">
    <div class="login-screen__inner">
        {{ $slot }}
    </div>
    @livewireScripts
</body>
</html>
