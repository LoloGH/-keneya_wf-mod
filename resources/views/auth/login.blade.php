<x-layouts.auth :title="'Connexion - '.config('keneya.name')">
    {{-- Sous 1024 px, la photo devient un bandeau et la carte passe dessous :
         la marque se pose alors sur le bandeau, comme sur la maquette. Elle
         est masquee sur grand ecran, ou l'image la porte deja. --}}
    <img class="login-band__marque"
         src="{{ asset('images/login-marque-bandeau.png') }}"
         alt="{{ config('keneya.name') }} - Espace professionnel"
         width="910" height="180">

    {{-- L'enveloppe porte le remplissage et sert d'echelle : la carte,
         elle, est le conteneur de requetes, et un conteneur ne peut pas
         exprimer son propre remplissage dans ses unites. --}}
    <div class="login-card">
        <div class="login-card__inner">
            {{-- Le bloc de marque de la maquette : symbole, nom du produit et
                 mention « Espace professionnel », dans une seule image. Il porte
                 son texte en alternative pour les lecteurs d'ecran. --}}
            <img class="login-card__marque"
                 src="{{ asset('images/login-marque.png') }}"
                 alt="{{ config('keneya.name') }} - Espace professionnel"
                 width="496" height="422">

            <div class="login-card__head">
                <h1 class="login-card__title">Bienvenue !</h1>
                <p class="login-card__subtitle">Connectez-vous a votre espace professionnel</p>
                <p class="login-card__site">
                    <x-icon name="etablissement" />
                    {{ hospital_name() }}
                </p>
            </div>

            {{-- Les messages du serveur sont repris tels quels, groupes en tete de
                 formulaire : identifiants refuses, champ manquant, trop de
                 tentatives. Les champs concernes portent aria-invalid. --}}
            @if ($errors->any())
                <div class="login-alert" role="alert">
                    <x-icon name="alerte-cercle" />
                    <span>
                        @foreach ($errors->all() as $message)
                            {{ $message }}@if (! $loop->last)<br>@endif
                        @endforeach
                    </span>
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="form login-form">
                @csrf

                <div class="field">
                    <label for="email">Nom d'utilisateur ou e-mail</label>
                    <div class="login-control">
                        <x-icon name="identifiant" class="login-control__icon" />
                        <input id="email" name="email" type="email" inputmode="email" autocomplete="username"
                               placeholder="Entrez votre identifiant"
                               value="{{ old('email') }}" required autofocus
                               @error('email') aria-invalid="true" @enderror>
                    </div>
                </div>

                <div class="field">
                    <label for="password">Mot de passe</label>
                    <div class="login-control">
                        <x-icon name="cadenas" class="login-control__icon" />
                        <input id="password" name="password" type="password" autocomplete="current-password"
                               placeholder="Entrez votre mot de passe" class="login-control__input--pw" required
                               @error('password') aria-invalid="true" @enderror>
                        {{-- Le bouton n'apparait que si le navigateur execute du script :
                             sans lui, un bouton inerte n'aurait aucun sens. --}}
                        <button type="button" class="login-peek" hidden data-peek aria-controls="password"
                                aria-pressed="false" aria-label="Afficher le mot de passe">
                            <x-icon name="oeil" data-icon="on" />
                            <x-icon name="oeil-barre" data-icon="off" />
                        </button>
                    </div>
                </div>

                <label class="login-remember">
                    <input type="checkbox" name="remember" value="1">
                    <span>Rester connecte sur ce poste</span>
                </label>

                <button type="submit" class="login-submit" data-submit>
                    <x-icon name="connexion" />
                    <span data-submit-label>Se connecter</span>
                </button>
            </form>

            <div class="login-card__foot">
                <span class="login-card__secure">
                    <x-icon name="securise" />
                    Connexion securisee
                </span>
            </div>

            {{-- Les quatre atouts de la maquette mobile. Sur grand ecran, ils sont
                 deja dans l'image de fond : inutile de les repeter. --}}
            <ul class="login-atouts">
                <li>
                    <x-icon name="securise" />
                    Securise
                </li>
                <li>
                    <x-icon name="rapide" />
                    Rapide
                </li>
                <li>
                    <x-icon name="centralise" />
                    Centralise
                </li>
                <li>
                    <x-icon name="performant" />
                    Performant
                </li>
            </ul>

            {{-- La mention d'editeur ferme la carte, sous les atouts sur mobile
                 et juste sous la ligne « connexion securisee » ailleurs. --}}
            <p class="login-card__rights">
                Tous droits reserves &copy; {{ date('Y') }}
                <a href="https://sukaxess.com" target="_blank" rel="noopener">AXESs</a>
            </p>
        </div>
    </div>

    {{-- Deux interactions, et rien de plus : afficher le mot de passe, et
         signaler l'envoi du formulaire. Aucun mot de passe n'est lu, stocke ou
         transmis par ce script : seul le type du champ change. --}}
    <script>
        (function () {
            var peek = document.querySelector('[data-peek]');
            var field = document.getElementById('password');

            if (peek && field) {
                peek.hidden = false;
                peek.addEventListener('click', function () {
                    var afficher = field.type === 'password';
                    field.type = afficher ? 'text' : 'password';
                    peek.setAttribute('aria-pressed', String(afficher));
                    peek.setAttribute('aria-label', afficher ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
                    field.focus();
                });
            }

            // Le bouton passe en « Connexion... » pendant que le serveur repond,
            // ce qui evite aussi la double soumission sur une liaison lente.
            var form = document.querySelector('.login-form');
            var bouton = document.querySelector('[data-submit]');
            var intitule = document.querySelector('[data-submit-label]');

            if (form && bouton && intitule) {
                form.addEventListener('submit', function () {
                    bouton.disabled = true;
                    bouton.setAttribute('aria-busy', 'true');
                    intitule.textContent = 'Connexion...';
                    var roue = document.createElement('span');
                    roue.className = 'login-spinner';
                    bouton.insertBefore(roue, intitule);
                });
            }
        })();
    </script>
</x-layouts.auth>
