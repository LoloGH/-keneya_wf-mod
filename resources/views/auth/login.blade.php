<x-layouts.auth :title="'Connexion - '.config('keneya.name')">
    @php
        /**
         * Ce que l'on fait dans l'outil, et non ce que l'outil vaut.
         *
         * La liste etait peinte dans la photographie jusqu'a la v3.3.1 : elle
         * n'etait ni lisible par un lecteur d'ecran, ni modifiable sans
         * repasser par un logiciel de dessin. Elle est declaree ici, en un
         * seul gabarit, comme sur l'ecran de connexion du DME.
         */
        $perimetre = [
            ['icone' => 'patient', 'intitule' => 'Accueil et enregistrement'],
            ['icone' => 'file', 'intitule' => 'File d\'attente'],
            ['icone' => 'soins', 'intitule' => 'Consultation'],
            ['icone' => 'tarif', 'intitule' => 'Caisse'],
            ['icone' => 'plateau', 'intitule' => 'Plateau technique'],
            ['icone' => 'document', 'intitule' => 'Dossier medical'],
        ];
    @endphp

    {{-- Le bloc de marque et l'accroche voyagent ensemble : ils occupent le
         haut de la colonne de gauche sur un poste fixe, et se posent en haut a
         droite du bandeau sur un telephone. Un seul element a deplacer. --}}
    <div class="login-intro">
        <div class="login-marque">
            <img src="{{ asset('images/keneya-logo.png') }}"
                 alt="{{ config('keneya.name') }} - Espace professionnel"
                 width="900" height="420">
            <span class="login-marque__filet" aria-hidden="true"></span>
            <span class="login-marque__mot">Espace<br>professionnel</span>
        </div>

        <div class="login-promesse">
            <h1>Le poste de travail<em>du soignant(e).</em></h1>
            <span class="login-filet" aria-hidden="true"></span>
        </div>
    </div>

    <section class="login-perimetre">
        <h2 class="login-perimetre__titre">Accueil, file d'attente, caisse et dossier patient au meme endroit</h2>
        <ul>
            @foreach ($perimetre as $entree)
                <li>
                    <x-icon :name="$entree['icone']" size="19" />
                    <span>{{ $entree['intitule'] }}</span>
                </li>
            @endforeach
        </ul>

        <p class="login-perimetre__mention">
            <x-icon name="bouclier" size="15" />
            Acces reserve au personnel de l'etablissement.
        </p>
    </section>

    <div class="login-carte">
        {{-- Le francais est la seule langue servie a ce jour. L'entree anglaise
             est annoncee plutot que masquee : mieux vaut poser le choix
             maintenant que faire croire qu'il n'existera jamais. --}}
        <details class="login-langue">
            <summary>
                <span>Francais</span>
                <x-icon name="chevron" size="13" />
            </summary>
            <ul>
                <li>
                    <span class="login-langue__actif">Francais</span>
                    <x-icon name="valide" size="14" />
                </li>
                <li>
                    <span>English</span>
                    <em>Bientot</em>
                </li>
            </ul>
        </details>

        {{-- Sur un poste fixe la carte porte le logo ; sur un telephone il est
             deja sur le bandeau, et la carte ouvre sur l'accueil. --}}
        <img class="login-carte__logo" src="{{ asset('images/keneya-logo.png') }}"
             alt="{{ config('keneya.name') }}" width="900" height="420">
        <p class="login-carte__titre">Bienvenue !</p>

        <p class="login-carte__sous">Connectez-vous a votre espace professionnel</p>

        <p class="login-carte__site">
            <x-icon name="etablissement" size="15" />
            {{ hospital_name() }}
        </p>

        {{-- Les messages du serveur sont repris tels quels, groupes en tete de
             formulaire : identifiants refuses, champ manquant, trop de
             tentatives. Les champs concernes portent aria-invalid. --}}
        @if ($errors->any())
            <div class="login-alert" role="alert">
                <x-icon name="alerte-cercle" size="17" />
                <span>
                    @foreach ($errors->all() as $message)
                        {{ $message }}@if (! $loop->last)<br>@endif
                    @endforeach
                </span>
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="login-form">
            @csrf

            <div class="field">
                <label for="email">Nom d'utilisateur ou e-mail</label>
                <div class="login-control">
                    <x-icon name="identifiant" size="17" class="login-control__icon" />
                    <input id="email" name="email" type="email" inputmode="email" autocomplete="username"
                           placeholder="Entrez votre identifiant"
                           value="{{ old('email') }}" required autofocus
                           @error('email') aria-invalid="true" @enderror>
                </div>
            </div>

            <div class="field">
                <label for="password">Mot de passe</label>
                <div class="login-control">
                    <x-icon name="cadenas" size="17" class="login-control__icon" />
                    <input id="password" name="password" type="password" autocomplete="current-password"
                           placeholder="Entrez votre mot de passe" class="login-control__input--pw" required
                           @error('password') aria-invalid="true" @enderror>
                    {{-- Le bouton n'apparait que si le navigateur execute du script :
                         sans lui, un bouton inerte n'aurait aucun sens. --}}
                    <button type="button" class="login-peek" hidden data-peek aria-controls="password"
                            aria-pressed="false" aria-label="Afficher le mot de passe">
                        <x-icon name="oeil" size="17" data-icon="on" />
                        <x-icon name="oeil-barre" size="17" data-icon="off" />
                    </button>
                </div>
            </div>

            <label class="login-remember">
                <input type="checkbox" name="remember" value="1">
                <span>Rester connecte sur ce poste</span>
            </label>

            <button type="submit" class="login-submit" data-submit>
                <x-icon name="connexion" size="18" />
                <span data-submit-label>Se connecter</span>
            </button>
        </form>

        <p class="login-carte__secure">
            <x-icon name="cadenas" size="14" />
            Connexion securisee
        </p>

        {{-- La version aide au support : on sait ce que le poste execute avant
             meme de decrocher. --}}
        <p class="login-carte__version">
            {{ config('keneya.name') }} &middot; v{{ config('keneya.version') }}
        </p>

        <p class="login-card__rights">
            Tous droits reserves &copy; {{ date('Y') }}
            <a href="https://sukaxess.com" target="_blank" rel="noopener">AXESs</a>
        </p>
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
