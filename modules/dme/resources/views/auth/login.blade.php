<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#172554">
    <title>Connexion · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ \Keneya\Dme\Dme::asset('assets/logo-icon.png') }}">
    <link rel="preload" as="image" href="{{ \Keneya\Dme\Dme::asset('assets/login-scene.webp') }}" type="image/webp">
    {{-- Feuilles de style et scripts du module, publiés dans le
         répertoire public de l'hôte (vendor:publish --tag=dme-assets). --}}
    <link rel="stylesheet" href="{{ \Keneya\Dme\Dme::asset('build/app.css') }}">
    <script src="{{ \Keneya\Dme\Dme::asset('build/app.js') }}" defer></script>
</head>
<body class="h-full overflow-hidden bg-clinic-950">

@php
    /**
     * Périmètre fonctionnel présenté à gauche. La liste est déclarée ici
     * plutôt que répétée en balisage : six entrées, un seul gabarit.
     */
    $features = [
        ['icon' => 'users', 'label' => 'Gestion des patients'],
        ['icon' => 'stethoscope', 'label' => 'Consultations'],
        ['icon' => 'pill', 'label' => 'Ordonnances'],
        ['icon' => 'flask', 'label' => 'Laboratoire'],
        ['icon' => 'scan', 'label' => 'Imagerie'],
        ['icon' => 'bed', 'label' => 'Hospitalisation'],
    ];

    $trust = [
        ['icon' => 'shield', 'label' => 'Sécurisé'],
        ['icon' => 'bolt', 'label' => 'Fiable'],
        ['icon' => 'users', 'label' => 'Au service des patients'],
    ];
@endphp

{{-- Page figée à l'écran : jamais de défilement général (demande client).
     h-[100dvh] plutôt que h-screen : sur mobile, 100vh dépasse la zone
     réellement visible sous la barre d'adresse et rognerait la carte. --}}
<div class="relative flex h-[100dvh] flex-col overflow-hidden">

    {{-- Scène hospitalière, telle qu'elle a été fournie. La carte patient y
         reste incrustée et rien n'est superposé à cette zone ; le bas du
         visuel n'est ni retouché ni flouté. Seul le bandeau de confiance en
         a été retiré, puisqu'il est reconstruit en HTML plus bas : l'y
         laisser l'afficherait deux fois. En portrait, l'image est rognée
         horizontalement : on recentre alors sur le couloir, faute de quoi le
         praticien se retrouve décapité derrière la carte. --}}
    <picture>
        <source srcset="{{ \Keneya\Dme\Dme::asset('assets/login-scene.webp') }}" type="image/webp">
        <img src="{{ \Keneya\Dme\Dme::asset('assets/login-scene.jpg') }}" alt="" aria-hidden="true"
             width="1671" height="941" fetchpriority="high"
             class="absolute inset-0 h-full w-full object-cover object-[26%_center] lg:object-center">
    </picture>

    {{-- Voile : lisibilité du texte blanc sans éteindre la scène. --}}
    <div class="pointer-events-none absolute inset-0 bg-linear-to-r from-clinic-950/80 via-clinic-950/45 to-clinic-950/20
                lg:from-clinic-950/75 lg:via-clinic-950/25 lg:to-transparent" aria-hidden="true"></div>
    <div class="pointer-events-none absolute inset-x-0 bottom-0 h-48 bg-linear-to-t from-clinic-950/55 to-transparent"
         aria-hidden="true"></div>

    <div class="relative flex min-h-0 flex-1 flex-col overflow-y-auto">

        {{-- ── Identité ─────────────────────────────────────────────── --}}
        <header class="shrink-0 px-5 pt-5 sm:px-8 sm:pt-7 lg:px-12 lg:pt-9 xl:px-16 2xl:px-24
                       [@media(max-height:820px)]:pt-4 [@media(max-height:820px)]:lg:pt-4">
            <div class="flex items-center gap-3.5 lg:gap-4">
                <img src="{{ \Keneya\Dme\Dme::asset('assets/logo-icon.png') }}" alt="Keneya DME"
                     width="320" height="270"
                     class="h-12 w-auto drop-shadow-md sm:h-14 lg:h-16 xl:h-[4.5rem]
                            [@media(max-height:820px)]:h-11 [@media(max-height:820px)]:sm:h-12 [@media(max-height:820px)]:lg:h-[3.25rem] [@media(max-height:820px)]:xl:h-14">
                <div class="leading-tight">
                    <p class="text-xl font-bold tracking-tight text-white drop-shadow-sm sm:text-2xl lg:text-3xl xl:text-[2rem]">
                        Keneya DME
                    </p>
                    <p class="text-xs font-medium tracking-wide text-white/80 sm:text-sm lg:text-base">
                        Dossier Médical Électronique
                    </p>
                </div>
            </div>
        </header>

        <div class="flex min-h-0 flex-1 flex-col lg:flex-row lg:items-stretch">

            {{-- ── Panneau gauche, promesse et périmètre ────────────
                 Masqué sous 1024 px : la carte de connexion prime, et
                 aucune information n'y est unique à ce panneau. --}}
            <section class="hidden min-h-0 lg:flex lg:w-[50%] lg:flex-col lg:justify-center lg:py-6 lg:pr-8 lg:pl-12
                            xl:w-[52%] xl:pb-40 xl:pl-16 2xl:pl-24">
                <div class="max-w-xl">
                    {{-- Agrandi mais allégé : la graisse baisse quand la
                         taille monte, sinon le bloc devient massif. --}}
                    <h1 class="text-[2.1rem] leading-[1.12] font-semibold tracking-tight text-white drop-shadow-sm
                               xl:text-[3.1rem] xl:leading-[1.1] 2xl:text-[3.4rem]">
                        Tous vos dossiers médicaux,
                        <span class="block font-normal text-clinic-200">au même endroit.</span>
                    </h1>

                    <ul class="mt-7 grid grid-cols-2 gap-x-6 gap-y-3.5 xl:mt-11 xl:gap-x-8 xl:gap-y-4">
                        @foreach ($features as $feature)
                            <li class="flex items-center gap-3.5">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full
                                             text-white/90 ring-1 ring-white/30 xl:h-10 xl:w-10" aria-hidden="true">
                                    <x-dme::icon :name="$feature['icon']" class="h-[1.15rem] w-[1.15rem]"/>
                                </span>
                                <span class="text-[0.9rem] font-medium text-white drop-shadow-sm xl:text-[0.975rem]">
                                    {{ $feature['label'] }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>

            {{-- ── Carte de connexion ────────────────────────────────
                 Seul panneau opaque : c'est là que va l'attention. --}}
            <div class="flex min-h-0 flex-1 items-center justify-center px-4 pt-3 pb-5 sm:px-6 sm:pb-8
                        lg:justify-end lg:pr-12 xl:pr-16 2xl:pr-24">
                <div class="w-full max-w-[23.5rem] rounded-2xl bg-white p-6 ring-1 ring-black/5
                            shadow-[0_24px_60px_-24px_rgb(15_23_42/0.55)] sm:max-w-[24.5rem] sm:p-7
                            [@media(max-height:820px)]:p-5 [@media(max-height:820px)]:sm:p-5"
                     x-data="{
                         showPassword: false,
                         submitting: false,
                         langOpen: false,
                         lang: 'fr',
                         fillRole(email) {
                             $refs.email.value = email;
                             $refs.password.focus();
                         },
                     }">

                    {{-- Sélecteur de langue : discret, aligné à droite --}}
                    <div class="relative -mt-1 flex justify-end" @click.outside="langOpen = false">
                        <button type="button"
                                class="flex items-center gap-1 rounded-md px-1 py-0.5 text-xs font-medium text-ink-500
                                       transition hover:text-ink-700 focus-visible:outline-2 focus-visible:outline-offset-2
                                       focus-visible:outline-clinic-600"
                                @click="langOpen = !langOpen" :aria-expanded="langOpen.toString()" aria-haspopup="listbox">
                            <span x-text="lang === 'fr' ? 'Français' : 'English'"></span>
                            <x-dme::icon name="chevron-down" class="h-3 w-3"/>
                        </button>
                        <ul x-cloak x-show="langOpen" x-transition.origin.top.right
                            class="absolute top-full right-0 z-10 mt-1 w-32 overflow-hidden rounded-lg border border-ink-200
                                   bg-white py-1 text-left shadow-lg"
                            role="listbox" aria-label="Langue de l'interface">
                            <li role="option" :aria-selected="lang === 'fr'">
                                <button type="button"
                                        class="flex w-full items-center justify-between px-3 py-1.5 text-xs text-ink-700 hover:bg-ink-50"
                                        @click="lang = 'fr'; langOpen = false">
                                    Français
                                    <x-dme::icon name="check" class="h-3.5 w-3.5 text-keneya-600" x-show="lang === 'fr'"/>
                                </button>
                            </li>
                            <li role="option" :aria-selected="lang === 'en'">
                                <button type="button"
                                        class="flex w-full items-center justify-between px-3 py-1.5 text-xs text-ink-400 hover:bg-ink-50"
                                        @click="lang = 'en'; langOpen = false" title="Interface en anglais bientôt disponible">
                                    English
                                    <span class="text-[9px] font-medium tracking-wide text-ink-400 uppercase">Bientôt</span>
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="mt-1 flex flex-col items-center text-center">
                        <img src="{{ \Keneya\Dme\Dme::asset('assets/logo-icon.png') }}" alt="Keneya DME"
                             width="320" height="270" class="h-20 w-auto object-contain [@media(max-height:820px)]:h-16">
                        <p class="mt-3 text-base font-medium text-ink-600 [@media(max-height:820px)]:mt-2">Connectez-vous pour accéder à votre espace</p>
                    </div>

                    @if (session('status'))
                        <div class="mt-4 rounded-lg border border-keneya-500 bg-keneya-50 px-3 py-2 text-xs text-keneya-700"
                             role="status">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form action="{{ route('dme.login') }}" method="POST" class="mt-5 space-y-3.5 [@media(max-height:820px)]:mt-3 [@media(max-height:820px)]:space-y-2.5" @submit="submitting = true">
                        @csrf

                        <div>
                            <label for="email" class="k-label">Adresse e-mail ou nom d'utilisateur</label>
                            <div class="relative">
                                <x-dme::icon name="mail" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-400"/>
                                <input id="email" name="email" type="email" value="{{ old('email') }}" x-ref="email"
                                       class="k-input pl-10 @error('email') border-red-500 @enderror"
                                       required autofocus autocomplete="username"
                                       @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                            </div>
                            @error('email')
                                <p id="email-error" class="k-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password" class="k-label">Mot de passe</label>
                            <div class="relative">
                                <x-dme::icon name="lock" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-400"/>
                                <input id="password" name="password" :type="showPassword ? 'text' : 'password'" x-ref="password"
                                       class="k-input pr-11 pl-10 @error('password') border-red-500 @enderror"
                                       required autocomplete="current-password"
                                       @error('password') aria-invalid="true" @enderror>
                                <button type="button" @click="showPassword = !showPassword"
                                        class="absolute top-1/2 right-2 -translate-y-1/2 rounded-md p-1.5 text-ink-400 transition
                                               hover:bg-ink-100 hover:text-ink-600 focus-visible:outline-2
                                               focus-visible:outline-offset-2 focus-visible:outline-clinic-600"
                                        :aria-label="showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
                                        :aria-pressed="showPassword.toString()">
                                    <x-dme::icon name="eye" class="h-4 w-4" x-show="!showPassword"/>
                                    <x-dme::icon name="eye-off" class="h-4 w-4" x-show="showPassword" x-cloak/>
                                </button>
                            </div>
                            <x-dme::field-error name="password"/>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 text-xs">
                            <label class="flex items-center gap-2 text-ink-600">
                                <input type="checkbox" name="remember" value="1"
                                       class="h-3.5 w-3.5 rounded border-ink-300 text-clinic-600 focus:ring-clinic-500">
                                Se souvenir de moi
                            </label>
                            <button type="button"
                                    class="rounded-md font-medium text-clinic-600 transition hover:text-clinic-700
                                           focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-600"
                                    @click="$refs.forgotHelp.hidden = !$refs.forgotHelp.hidden"
                                    aria-controls="forgot-password-help" :aria-expanded="(!$refs.forgotHelp?.hidden).toString()">
                                Mot de passe oublié ?
                            </button>
                        </div>
                        <p x-ref="forgotHelp" id="forgot-password-help" hidden
                           class="k-hint rounded-lg bg-ink-50 px-3 py-2" role="note">
                            Contactez un administrateur pour réinitialiser votre mot de passe.
                        </p>

                        <button type="submit" :disabled="submitting"
                                class="k-btn w-full bg-linear-to-r from-clinic-700 to-keneya-600 text-white transition
                                       hover:from-clinic-800 hover:to-keneya-700 focus-visible:outline-clinic-700
                                       disabled:opacity-80">
                            <x-dme::icon name="login" class="h-4 w-4" x-show="!submitting"/>
                            <svg x-show="submitting" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <span x-text="submitting ? 'Connexion en cours...' : 'Se connecter'"></span>
                        </button>
                    </form>

                    <div class="mt-5 flex items-center gap-3 text-[11px] font-medium text-ink-400 [@media(max-height:820px)]:mt-3" role="separator">
                        <span class="h-px flex-1 bg-ink-200"></span>
                        ou
                        <span class="h-px flex-1 bg-ink-200"></span>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4 [@media(max-height:820px)]:mt-2">
                        <button type="button"
                                class="flex min-h-9 [@media(max-height:820px)]:min-h-8 items-center justify-center gap-1.5 rounded-lg border border-ink-200 px-2
                                       text-[11px] font-medium text-ink-500 transition hover:border-clinic-300
                                       hover:bg-clinic-50 hover:text-clinic-700 focus-visible:outline-2
                                       focus-visible:outline-offset-2 focus-visible:outline-clinic-600"
                                @click="fillRole('medecin@keneya.test')">
                            <x-dme::icon name="stethoscope" class="h-3.5 w-3.5"/> Médecin
                        </button>
                        <button type="button"
                                class="flex min-h-9 [@media(max-height:820px)]:min-h-8 items-center justify-center gap-1.5 rounded-lg border border-ink-200 px-2
                                       text-[11px] font-medium text-ink-500 transition hover:border-clinic-300
                                       hover:bg-clinic-50 hover:text-clinic-700 focus-visible:outline-2
                                       focus-visible:outline-offset-2 focus-visible:outline-clinic-600"
                                @click="fillRole('infirmier@keneya.test')">
                            <x-dme::icon name="heart" class="h-3.5 w-3.5"/> Infirmier
                        </button>
                        <button type="button"
                                class="flex min-h-9 [@media(max-height:820px)]:min-h-8 items-center justify-center gap-1.5 rounded-lg border border-ink-200 px-2
                                       text-[11px] font-medium text-ink-500 transition hover:border-clinic-300
                                       hover:bg-clinic-50 hover:text-clinic-700 focus-visible:outline-2
                                       focus-visible:outline-offset-2 focus-visible:outline-clinic-600"
                                @click="fillRole('reception@keneya.test')">
                            <x-dme::icon name="clipboard" class="h-3.5 w-3.5"/> Réception
                        </button>
                        <button type="button"
                                class="flex min-h-9 [@media(max-height:820px)]:min-h-8 items-center justify-center gap-1.5 rounded-lg border border-ink-200 px-2
                                       text-[11px] font-medium text-ink-500 transition hover:border-clinic-300
                                       hover:bg-clinic-50 hover:text-clinic-700 focus-visible:outline-2
                                       focus-visible:outline-offset-2 focus-visible:outline-clinic-600"
                                @click="fillRole('admin@keneya.test')">
                            <x-dme::icon name="cog" class="h-3.5 w-3.5"/> Admin
                        </button>
                    </div>

                    <p class="mt-5 flex items-center justify-center gap-1.5 text-center text-[11px] text-ink-400 [@media(max-height:820px)]:mt-3">
                        <x-dme::icon name="lock" class="h-3 w-3 shrink-0"/>
                        Accès sécurisé
                    </p>

                    <p class="mt-2.5 text-center text-[11px] text-ink-300 [@media(max-height:820px)]:mt-1.5">
                        © {{ date('Y') }} Keneya DME · v{{ config('dme.version') }}
                    </p>
                </div>
            </div>
        </div>

        {{-- ── Mentions de confiance, discrètes, sans bandeau ────────── --}}
        <footer class="hidden shrink-0 px-12 pb-6 lg:block xl:px-16 2xl:px-24 [@media(max-height:820px)]:pb-3">
            <ul class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-white/75">
                @foreach ($trust as $index => $item)
                    @if ($index > 0)
                        <li class="h-3 w-px bg-white/25" aria-hidden="true"></li>
                    @endif
                    <li class="flex items-center gap-2 drop-shadow-sm">
                        <x-dme::icon :name="$item['icon']" class="h-4 w-4 text-white/60"/>
                        {{ $item['label'] }}
                    </li>
                @endforeach
            </ul>
        </footer>
    </div>

</div>
</body>
</html>
