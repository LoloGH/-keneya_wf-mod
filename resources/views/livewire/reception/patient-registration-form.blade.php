<x-card title="Enregistrer un patient" icon="ajouter">
    <p class="hint">Pour un patient qui n'est jamais venu. Un nouvel identifiant lui sera attribue.</p>

    {{-- Doublon probable (v3.2.8, point 1).
         Interrompt la creation tant que la receptionniste n'a pas tranche :
         c'est elle qui a la personne devant elle, pas l'application. --}}
    @if ($duplicateCandidates !== [])
        <div class="doublon" role="alertdialog" aria-labelledby="doublon-titre">
            <h3 class="doublon__titre" id="doublon-titre">
                {{ count($duplicateCandidates) > 1 ? 'Des dossiers existent deja' : 'Un dossier existe deja' }}
                pour cette personne
            </h3>
            <p class="hint">
                Creer un second dossier donnerait un deuxieme identifiant a la
                meme personne. Verifiez avant de continuer.
            </p>

            <ul class="doublon__liste">
                @foreach ($duplicateCandidates as $candidat)
                    <li class="doublon__dossier">
                        <div>
                            <p class="doublon__nom">
                                {{ $candidat['name'] }}
                                <span class="mono">{{ $candidat['patient_code'] }}</span>
                            </p>
                            <p class="doublon__detail">
                                {{ $candidat['age'] }} ans - {{ $candidat['mobile'] }}
                                @if ($candidat['profession']) - {{ $candidat['profession'] }} @endif
                                @if ($candidat['last_visit']), derniere venue le {{ $candidat['last_visit'] }} @endif
                            </p>
                        </div>

                        <button type="button" class="btn btn--primary"
                                wire:click="openEpisodeForExisting({{ $candidat['id'] }})"
                                wire:loading.attr="disabled">
                            C'est la meme personne, ouvrir un nouvel episode
                        </button>
                    </li>
                @endforeach
            </ul>

            <div class="btn-row">
                <button type="button" class="btn btn--secondary" wire:click="createAnyway"
                        wire:loading.attr="disabled">
                    C'est une personne differente, creer quand meme un dossier
                </button>
                <button type="button" class="btn btn--ghost" wire:click="dismissDuplicates">
                    Revenir a la saisie
                </button>
            </div>

            <p class="hint">
                Creer malgre tout est possible et parfois justifie ; la decision
                est simplement inscrite au journal d'audit.
            </p>
        </div>
    @endif

    <form wire:submit="save" class="form">
        {{-- Le formulaire est groupe par nature de renseignement : ce que la
             receptionniste demande au patient, puis ce qui concerne sa venue du
             jour. Une colonne unique de neuf champs obligeait a faire defiler
             tout l'ecran pour un enregistrement de trente secondes. --}}
        <fieldset class="formset">
            <legend>Identite du patient</legend>

            <div class="field">
                <label for="patient-name">Nom complet</label>
                <input id="patient-name" type="text" wire:model="name" autocomplete="off">
                @error('name') <p class="field__error">{{ $message }}</p> @enderror
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="patient-age">Age</label>
                    <input id="patient-age" type="number" inputmode="numeric" min="0" max="130" wire:model="age">
                    @error('age') <p class="field__error">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label for="patient-gender">Sexe</label>
                    <select id="patient-gender" wire:model="gender">
                        <option value="Homme">Homme</option>
                        <option value="Femme">Femme</option>
                    </select>
                    @error('gender') <p class="field__error">{{ $message }}</p> @enderror
                </div>

                <div class="field field--wide">
                    <label for="patient-profession">Profession</label>
                    <input id="patient-profession" type="text" wire:model="profession"
                           placeholder="cultivateur, enseignante...">
                    @error('profession') <p class="field__error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="patient-mobile">Telephone</label>
                    <input id="patient-mobile" type="tel" inputmode="tel" wire:model="mobile" placeholder="76 00 00 00">
                    @error('mobile') <p class="field__error">{{ $message }}</p> @enderror
                </div>

                {{-- Le seul champ qui distingue deux personnes a coup sur, et
                     c'est a ce titre que la recherche de doublon le consulte
                     en premier. Facultatif, et il doit le rester : un patient
                     arrive sans papiers doit etre enregistre quand meme. --}}
                <div class="field">
                    <label for="patient-id-card">
                        N&deg; de la carte d'identite <span class="field__hint">(facultatif)</span>
                    </label>
                    <input id="patient-id-card" type="text" wire:model="idCardNumber"
                           autocomplete="off" placeholder="Recommande : evite les doublons">
                    @error('idCardNumber') <p class="field__error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="patient-crno">
                        Numero de dossier papier <span class="field__hint">(facultatif)</span>
                    </label>
                    <input id="patient-crno" type="text" wire:model="crno">
                    @error('crno') <p class="field__error">{{ $message }}</p> @enderror
                </div>
            </div>
        </fieldset>

        <fieldset class="formset">
            <legend>Passage du jour</legend>

            <div class="field-row">
                <div class="field">
                    <label for="patient-service">Service</label>
                    <select id="patient-service" wire:model="service_id">
                        <option value="">Choisir un service</option>
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}">{{ $service->name }}</option>
                        @endforeach
                    </select>
                    @error('service_id') <p class="field__error">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label for="patient-reason">Motif <span class="field__hint">(facultatif)</span></label>
                    <input id="patient-reason" type="text" wire:model="reason">
                    @error('reason') <p class="field__error">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- La note vit sur le dossier, pas sur le passage : ce qu'elle
                 porte reste vrai a la venue suivante. --}}
            <div class="field">
                <label for="patient-note">
                    Note pour le service <span class="field__hint">(facultatif)</span>
                </label>
                <textarea id="patient-note" rows="2" wire:model="note"
                          placeholder="Malentendant, accompagne par sa fille, vient de Kita..."></textarea>
                @error('note') <p class="field__error">{{ $message }}</p> @enderror
            </div>
        </fieldset>

        {{-- Accompagnateurs : information non medicale, sans ticket propre. --}}
        <fieldset class="companions-fieldset">
            <legend>Accompagnateurs <span class="field__hint">(facultatif)</span></legend>

            @foreach ($companions as $index => $companion)
                <div class="field-row companions-fieldset__row" wire:key="companion-{{ $index }}">
                    <div class="field">
                        <label for="companion-name-{{ $index }}">Nom</label>
                        <input id="companion-name-{{ $index }}" type="text" wire:model="companions.{{ $index }}.name">
                        @error("companions.$index.name") <p class="field__error">{{ $message }}</p> @enderror
                    </div>
                    <div class="field">
                        <label for="companion-relation-{{ $index }}">Lien</label>
                        <input id="companion-relation-{{ $index }}" type="text"
                               wire:model="companions.{{ $index }}.relation" placeholder="epoux, mere...">
                    </div>
                    <div class="field">
                        <label for="companion-phone-{{ $index }}">Telephone</label>
                        <input id="companion-phone-{{ $index }}" type="tel" wire:model="companions.{{ $index }}.phone">
                    </div>
                    <button type="button" class="btn btn--ghost" wire:click="removeCompanion({{ $index }})">Retirer</button>
                </div>
            @endforeach

            @if (count($companions) < 5)
                <button type="button" class="btn btn--secondary" wire:click="addCompanion">
                    Ajouter un accompagnateur
                </button>
            @endif
        </fieldset>

        <button type="submit" class="btn btn--primary btn--block" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="save">Enregistrer le patient</span>
            <span wire:loading wire:target="save">Enregistrement...</span>
        </button>
    </form>

    @if ($lastRegistered)
        <div class="ticket" role="status">
            <p class="ticket__label">N&deg; patient</p>
            <p class="ticket__code">{{ $lastRegistered['patient_code'] }}</p>
            <p class="ticket__meta">
                {{ $lastRegistered['name'] }} - {{ $lastRegistered['service'] }},
                ticket n° {{ $lastRegistered['token'] }}
            </p>
            @if ($lastRegistered['pending'])
                <p class="ticket__meta">Puis : {{ $lastRegistered['pending'] }}, apres paiement.</p>
            @endif

            {{-- Le code a communiquer au patient pour qu'il consulte ses
                 documents en ligne. Il figure aussi sur le ticket imprime. --}}
            <p class="ticket__label">Code personnel</p>
            <p class="ticket__code">{{ $lastRegistered['access_code'] }}</p>

            <div class="btn-row btn-row--centered">
                <a href="{{ route('reception.ticket.patient', $lastRegistered['visit_id']) }}"
                   target="_blank" class="btn btn--primary">Imprimer le ticket</a>
                <button type="button" class="btn btn--secondary" wire:click="sendPortalLink">
                    Envoyer le lien de mes documents
                </button>
            </div>
        </div>
    @endif
</x-card>
