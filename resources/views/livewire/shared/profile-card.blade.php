{{-- Carte de profil (v3.2.3, point 3).

     Elle remplace l'icone de deconnexion isolee : la deconnexion y est
     toujours, avec ce qui lui manquait — savoir de quel compte il s'agit, et
     pouvoir en changer le mot de passe. --}}
<div class="profil" @keydown.escape.window="$wire.close()">

    <button type="button" class="profil__avatar" wire:click="toggle"
            data-testid="profile-card"
            aria-haspopup="true" aria-expanded="{{ $open ? 'true' : 'false' }}"
            aria-label="Mon compte — {{ $user?->name }}" title="Mon compte">
        {{ $initials }}
    </button>

    @if ($open)
        <div class="profil__panel" role="dialog" aria-label="Mon compte">
            {{-- En-tete : l'avatar redit qui l'on est, pour que la carte se
                 suffise a elle-meme une fois ouverte par-dessus l'ecran. --}}
            <div class="profil__head">
                <span class="profil__avatar profil__avatar--large" aria-hidden="true">{{ $initials }}</span>
                <span class="profil__who">
                    <strong>{{ $user?->name }}</strong>
                    <span class="profil__role">{{ $roleLabel ?: '—' }}</span>
                </span>
                <button type="button" class="alert__dismiss" wire:click="close"
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
                 /admin — il n'appartient a personne en particulier. --}}
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
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M15 17l5-5-5-5" />
                                <path d="M20 12H9" />
                                <path d="M11 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5" />
                            </svg>
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
    @endif
</div>
