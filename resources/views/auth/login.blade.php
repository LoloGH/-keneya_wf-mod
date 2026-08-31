<x-layouts.auth :title="'Connexion — '.config('keneya.name')">
    {{-- Sous 1024 px, la photo devient un bandeau et la carte passe dessous :
         la marque se pose alors sur le bandeau, comme sur la maquette. Elle
         est masquee sur grand ecran, ou l'image la porte deja. --}}
    <img class="login-band__marque"
         src="{{ asset('images/login-marque-bandeau.png') }}"
         alt="{{ config('keneya.name') }} — Espace professionnel"
         width="910" height="180">

    {{-- Le discours de la scene. Il etait peint dans l'image ; il est
         desormais du texte : traduisible, net a toutes les tailles, et
         corrigible sans repasser par une retouche graphique. --}}
    <div class="login-hero">
        <h2 class="login-hero__title">Le poste de travail du soignant.</h2>
        <p class="login-hero__sub">Accueil, file d'attente, caisse et dossier patient au meme endroit.</p>
    </div>

    <ul class="login-atouts login-atouts--scene">
            <li>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 3l7.5 3v5.5c0 4.4-3.1 8.3-7.5 9.5-4.4-1.2-7.5-5.1-7.5-9.5V6Z" /><path d="M8.8 12.2l2.2 2.2 4.2-4.4" />
                </svg>
                <span><b>Securise</b>Vos donnees sont protegees</span>
            </li>
            <li>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M13 2 4.5 13.5H11l-1 8.5 8.5-11.5H12l1-8.5Z" />
                </svg>
                <span><b>Rapide</b>Accedez a vos outils en quelques clics</span>
            </li>
            <li>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="8.5" /><circle cx="12" cy="12" r="3.5" /><path d="M12 1.5v3M12 19.5v3M1.5 12h3M19.5 12h3" />
                </svg>
                <span><b>Centralise</b>Toutes vos activites au meme endroit</span>
            </li>
            <li>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 3.5l2.6 5.3 5.9.9-4.2 4.1 1 5.8-5.3-2.8-5.3 2.8 1-5.8-4.2-4.1 5.9-.9Z" />
                </svg>
                <span><b>Performant</b>Concu pour les professionnels</span>
            </li>
    </ul>

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
                 alt="{{ config('keneya.name') }} — Espace professionnel"
                 width="680" height="484">

            <div class="login-card__head">
                <h1 class="login-card__title">Bienvenue !</h1>
                <p class="login-card__subtitle">Connectez-vous a votre espace professionnel</p>
            </div>

            {{-- Les messages du serveur sont repris tels quels, groupes en tete de
                 formulaire : identifiants refuses, champ manquant, trop de
                 tentatives. Les champs concernes portent aria-invalid. --}}
            @if ($errors->any())
                <div class="login-alert" role="alert">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" /><path d="M12 7.5v5M12 16.2h.01" />
                    </svg>
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
                        <svg class="login-control__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="8" r="3.6" /><path d="M4.5 20c.6-3.8 3.7-6 7.5-6s6.9 2.2 7.5 6" />
                        </svg>
                        <input id="email" name="email" type="email" inputmode="email" autocomplete="username"
                               placeholder="Entrez votre identifiant"
                               value="{{ old('email') }}" required autofocus
                               @error('email') aria-invalid="true" @enderror>
                    </div>
                </div>

                <div class="field">
                    <label for="password">Mot de passe</label>
                    <div class="login-control">
                        <svg class="login-control__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="4.5" y="10" width="15" height="10.5" rx="2.5" />
                            <path d="M8 10V7.5a4 4 0 0 1 8 0V10" />
                        </svg>
                        <input id="password" name="password" type="password" autocomplete="current-password"
                               placeholder="Entrez votre mot de passe" class="login-control__input--pw" required
                               @error('password') aria-invalid="true" @enderror>
                        {{-- Le bouton n'apparait que si le navigateur execute du script :
                             sans lui, un bouton inerte n'aurait aucun sens. --}}
                        <button type="button" class="login-peek" hidden data-peek aria-controls="password"
                                aria-pressed="false" aria-label="Afficher le mot de passe">
                            <svg data-icon="on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                            <svg data-icon="off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M4 4l16 16" />
                                <path d="M9.9 5.8A9.6 9.6 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a17 17 0 0 1-3.3 4.1M6.4 7.9A17 17 0 0 0 2.5 12S6 18.5 12 18.5c1 0 1.9-.2 2.7-.5" />
                                <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="login-submit" data-submit>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5" />
                        <path d="M10 17l5-5-5-5" /><path d="M15 12H3" />
                    </svg>
                    <span data-submit-label>Se connecter</span>
                </button>
            </form>

            <div class="login-card__foot">
                <span class="login-card__secure">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 3l7.5 3v5.5c0 4.4-3.1 8.3-7.5 9.5-4.4-1.2-7.5-5.1-7.5-9.5V6Z" />
                        <path d="M8.8 12.2l2.2 2.2 4.2-4.4" />
                    </svg>
                    Connexion securisee
                </span>
                <p class="login-card__rights">
                    Tous droits reserves &copy; {{ date('Y') }}
                    <a href="https://sukaxess.com" target="_blank" rel="noopener">AXESs</a>
                </p>
            </div>

            {{-- Les quatre atouts de la maquette mobile. Sur grand ecran, ils sont
                 deja dans l'image de fond : inutile de les repeter. --}}
            <ul class="login-atouts">
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 3l7.5 3v5.5c0 4.4-3.1 8.3-7.5 9.5-4.4-1.2-7.5-5.1-7.5-9.5V6Z" />
                        <path d="M8.8 12.2l2.2 2.2 4.2-4.4" />
                    </svg>
                    Securise
                </li>
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M13 2 4.5 13.5H11l-1 8.5 8.5-11.5H12l1-8.5Z" />
                    </svg>
                    Rapide
                </li>
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="8.5" /><circle cx="12" cy="12" r="3.5" />
                        <path d="M12 1.5v3M12 19.5v3M1.5 12h3M19.5 12h3" />
                    </svg>
                    Centralise
                </li>
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 3.5l2.6 5.3 5.9.9-4.2 4.1 1 5.8-5.3-2.8-5.3 2.8 1-5.8-4.2-4.1 5.9-.9Z" />
                    </svg>
                    Performant
                </li>
            </ul>
        </div>
    </div>

    {{-- Deux interactions, et rien de plus : afficher le mot de passe, et
         signaler l'envoi du formulaire. Aucun mot de passe n'est lu, stocke ou
         transmis par ce script — seul le type du champ change. --}}
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
