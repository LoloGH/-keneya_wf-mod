{{-- Carte de profil (v3.2.3, point 3).

     Elle remplace l'icone de deconnexion isolee : la deconnexion y est
     toujours, avec ce qui lui manquait, savoir de quel compte il s'agit, et
     pouvoir en changer le mot de passe. --}}
<div class="profil"
     x-data="{
        ...survol({ entree: 0 }),

        // La carte est rendue d'avance et seulement masquee : c'est le
        // navigateur qui l'ouvre, sans rien demander a personne. `$wire` suit
        // derriere pour ce qui lui revient — replier les formulaires quand la
        // carte se ferme. Son contenu ne coute rien de plus a preparer : le
        // composant calculait deja le nom, la fonction, le service et les
        // initiales a chaque rendu, ouvert ou non.
        ouvert: @js($open),

        basculer() {
            this.ouvert = ! this.ouvert;
            this.ouvert ? this.$wire.toggle() : this.$wire.close();
        },
        survolOuvre() {
            this.ouvert = true;
            if (! this.$wire.open) this.$wire.toggle();
        },
        survolFerme() {
            this.ouvert = false;
            if (this.$wire.open) this.$wire.close();
        },

        // La carte ne se referme pas toute seule sur un formulaire commence :
        // fermer remet a zero le changement de mot de passe et le depot de
        // signature, et un curseur qui s'ecarte le temps de lire un post-il
        // effacerait une saisie en cours. Le bouton de fermeture, la touche
        // d'echappement et « Annuler » restent tous trois disponibles.
        survolRetenu() {
            return this.$wire.changingPassword || this.$wire.editingSignature;
        },
     }"
     @pointerenter="survolEntre($event)"
     @pointerleave="survolSort($event)"
     @keydown.escape.window="ouvert = false; $wire.close()">

    <button type="button" class="profil__avatar" @click="basculer()"
            data-testid="profile-card"
            aria-haspopup="true" :aria-expanded="ouvert ? 'true' : 'false'"
            aria-label="Mon compte - {{ $user?->name }}" title="Mon compte">
        {{ $initials }}
    </button>

    {{-- `x-cloak` couvre le temps qui separe l'affichage de la page du
         demarrage d'Alpine : sans lui, la carte apparaitrait une fraction de
         seconde a chaque chargement. --}}
    <div class="profil__panel" role="dialog" aria-label="Mon compte"
         x-show="ouvert" x-cloak>
        {{-- En-tete : l'avatar redit qui l'on est, pour que la carte se
             suffise a elle-meme une fois ouverte par-dessus l'ecran. --}}
        <div class="profil__head">
            <span class="profil__avatar profil__avatar--large" aria-hidden="true">{{ $initials }}</span>
            <span class="profil__who">
                <strong>{{ $user?->name }}</strong>
                <span class="profil__role">{{ $roleLabel ?: '-' }}</span>
            </span>
            <button type="button" class="alert__dismiss"
                    @click="ouvert = false" wire:click="close"
                    aria-label="Fermer" title="Fermer">&times;</button>
        </div>

        <dl class="profil__identity">
            {{-- Une receptionniste ou un caissier n'est rattache a aucun
                 service : la ligne s'efface plutot que d'afficher un tiret. --}}
            @if ($serviceLabel)
                <div>
                    <dt>Service</dt>
                    <dd>{{ $serviceLabel }}</dd>
                </div>
            @endif

            <div>
                <dt>Adresse e-mail</dt>
                <dd>{{ $user?->email }}</dd>
            </div>
        </dl>

        @if (session('profil.status'))
            <div class="alert alert--success" role="status">{{ session('profil.status') }}</div>
        @endif

        {{-- Signature et tampon (v3.2.9, point 2) : offerts au seul
             medecin, puisque ce sont les elements qu'il appose sur ses
             ordonnances. Le tampon de l'etablissement, lui, se regle dans
             /admin : il n'appartient a personne en particulier. --}}
        @if ($isDoctor && $editingSignature)
            <form wire:submit="saveSignature" class="form profil__form">
                <div class="field">
                    <label for="profil-signature">
                        Signature
                        <span class="field__hint">PNG, JPEG ou WebP. 2 Mo maximum</span>
                    </label>
                    <input id="profil-signature" type="file" accept="image/png,image/jpeg,image/webp"
                           wire:model="signatureFile">
                    @error('signatureFile') <p class="field__error">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label for="profil-tampon">
                        Tampon personnel
                        <span class="field__hint">PNG, JPEG ou WebP. 2 Mo maximum</span>
                    </label>
                    <input id="profil-tampon" type="file" accept="image/png,image/jpeg,image/webp"
                           wire:model="stampFile">
                    @error('stampFile') <p class="field__error">{{ $message }}</p> @enderror
                </div>

                <p class="field__hint">
                    Toute modification est inscrite au journal d'audit.
                </p>

                <div class="profil__actions">
                    <button type="submit" class="btn btn--primary profil__grow"
                            wire:loading.attr="disabled">Enregistrer</button>
                    <button type="button" class="btn btn--ghost"
                            wire:click="closeSignatureForm">Annuler</button>
                </div>
            </form>
        @elseif (! $changingPassword)
            <div class="profil__actions">
                {{-- Les actions du compte s'empilent, elles ne se partagent
                     pas une rangee : cote a cote, « Signature et tampon » et
                     « Changer le mot de passe » debordaient de la carte des
                     que le medecin voyait les deux (v3.2.9). Une colonne
                     reste juste quel qu'en soit le nombre. --}}
                <div class="profil__links">
                    @if ($isDoctor)
                        <button type="button" class="btn btn--secondary"
                                wire:click="startSignatureChange">Signature et tampon</button>
                    @endif
                    <button type="button" class="btn btn--secondary"
                            wire:click="startPasswordChange">Changer le mot de passe</button>
                </div>

                {{-- Icone seule, comme les autres actions de la barre : le
                     libelle reste porte par aria-label et title. --}}
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="icon-btn icon-btn--danger" data-testid="logout"
                            aria-label="Se deconnecter" title="Se deconnecter">
                        <x-icon name="deconnexion" size="22" />
                    </button>
                </form>
            </div>
        @endif

        @if ($changingPassword)
            <form wire:submit="changePassword" class="form profil__form">
                <div class="field">
                    <label for="profil-current">Mot de passe actuel</label>
                    <input id="profil-current" type="password" autocomplete="current-password"
                           wire:model="current_password">
                    @error('current_password') <p class="field__error">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label for="profil-new">
                        Nouveau mot de passe
                        <span class="field__hint">8 caracteres minimum, lettres et chiffres</span>
                    </label>
                    <input id="profil-new" type="password" autocomplete="new-password"
                           wire:model="password">
                    @error('password') <p class="field__error">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label for="profil-confirm">Confirmer le nouveau mot de passe</label>
                    <input id="profil-confirm" type="password" autocomplete="new-password"
                           wire:model="password_confirmation">
                </div>

                <p class="field__hint">
                    Les autres sessions ouvertes avec ce compte seront fermees.
                </p>

                <div class="btn-row">
                    <button type="submit" class="btn btn--primary">Enregistrer</button>
                    <button type="button" class="btn btn--ghost"
                            wire:click="closePasswordForm">Annuler</button>
                </div>
            </form>
        @endif
    </div>
</div>
