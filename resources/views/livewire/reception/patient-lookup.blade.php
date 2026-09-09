<x-card class="cardcard--accent" title="Le patient est-il deja venu ?" icon="recherche">
    <p class="hint">Cherchez avant d'enregistrer : un patient deja connu garde son identifiant a vie.</p>

    <div class="field">
        <label for="lookup-search">N&deg; patient, nom ou telephone</label>
        <input id="lookup-search" type="search" wire:model.live.debounce.400ms="search"
               placeholder="HFD-00001, Sekou Diarra, 76445566...">
    </div>

    @if (trim($search) !== '' && $matches->isEmpty())
        <p class="empty">
            Aucun dossier ne correspond. Utilisez le formulaire « Enregistrer un patient » ci-dessous
            pour enregistrer un nouveau patient.
        </p>
    @endif

    @if ($matches->isNotEmpty() && ! $selected)
        <ul class="lookup">
            @foreach ($matches as $match)
                <li class="lookup__item">
                    <span class="lookup__identity">
                        <strong>{{ $match->name }}</strong>
                        <span class="mono">{{ $match->patient_code }}</span>
                        <span>{{ $match->age }} ans - {{ $match->gender }} - {{ $match->mobile }}</span>
                    </span>
                    <button type="button" class="btn btn--secondary" wire:click="select({{ $match->id }})">
                        C'est ce patient
                    </button>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($selected)
        {{-- Confirmation visuelle obligatoire : deux homonymes ne doivent
             jamais etre confondus. --}}
        <div class="lookup__confirm">
            <h3 class="card__subtitle">Confirmer l'identite</h3>

            <dl class="record__identity">
                <div><dt>Nom</dt><dd>{{ $selected->name }}</dd></div>
                <div><dt>N&deg; patient</dt><dd class="mono">{{ $selected->patient_code }}</dd></div>
                <div><dt>Age</dt><dd>{{ $selected->age }} ans</dd></div>
                <div><dt>Sexe</dt><dd>{{ $selected->gender }}</dd></div>
                <div><dt>Telephone</dt><dd>{{ $selected->mobile }}</dd></div>
                <div><dt>Carte d'identite</dt><dd>{{ $selected->id_card_number ?: '-' }}</dd></div>
                <div><dt>Passages</dt><dd>{{ $selected->visits->count() }}</dd></div>
            </dl>

            {{-- Correction de l'identite (v3.3.1).

                 Un nom mal orthographie, un numero qui a change, une carte
                 relevee apres coup : sans correction possible, la seule issue
                 serait d'ouvrir un second dossier pour la meme personne, ce
                 que tout le reste du produit s'emploie a empecher.

                 Cinq champs, et l'identifiant du patient n'en fait pas partie. --}}
            @if ($correctingPatientId === $selected->id)
                <form wire:submit="saveCorrection" class="form lookup__correction">
                    <p class="hint">
                        L'identifiant {{ $selected->patient_code }} ne change pas :
                        on corrige une identite, on n'en cree pas une seconde.
                    </p>

                    <div class="field-row">
                        <div class="field">
                            <label for="correction-name">Nom complet</label>
                            <input id="correction-name" type="text" wire:model="correctionName">
                            @error('correctionName') <p class="field__error">{{ $message }}</p> @enderror
                        </div>

                        <div class="field">
                            <label for="correction-gender">Sexe</label>
                            <select id="correction-gender" wire:model="correctionGender">
                                <option value="Homme">Homme</option>
                                <option value="Femme">Femme</option>
                            </select>
                            @error('correctionGender') <p class="field__error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label for="correction-mobile">Telephone</label>
                            <input id="correction-mobile" type="tel" inputmode="tel" wire:model="correctionMobile">
                            @error('correctionMobile') <p class="field__error">{{ $message }}</p> @enderror
                        </div>

                        <div class="field">
                            <label for="correction-profession">
                                Profession <span class="field__hint">(facultatif)</span>
                            </label>
                            <input id="correction-profession" type="text" wire:model="correctionProfession">
                            @error('correctionProfession') <p class="field__error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="field">
                        <label for="correction-id-card">
                            N&deg; de la carte d'identite <span class="field__hint">(facultatif)</span>
                        </label>
                        <input id="correction-id-card" type="text" wire:model="correctionIdCardNumber"
                               autocomplete="off" placeholder="Recommande : evite les doublons">
                        @error('correctionIdCardNumber') <p class="field__error">{{ $message }}</p> @enderror
                    </div>

                    <div class="btn-row">
                        <button type="submit" class="btn btn--primary">Enregistrer la correction</button>
                        <button type="button" class="btn btn--ghost" wire:click="cancelCorrection">Annuler</button>
                    </div>
                </form>
            @else
                <div class="btn-row">
                    <button type="button" class="btn btn--ghost"
                            wire:click="startCorrection({{ $selected->id }})">
                        Corriger l'identite
                    </button>
                </div>
            @endif

            @php $openVisit = $selected->visits->whereIn('status', $openStatuses)->sortByDesc('opened_at')->first(); @endphp

            @if ($openVisit)
                <p class="alert alert--warning" role="status">
                    Ce patient a deja un episode en cours au service {{ $openVisit->service->name }}
                    (ticket n° {{ $openVisit->token }}). Ouvrir un nouvel episode le placera dans une seconde file.
                </p>
            @endif

            <form wire:submit="openEpisode" class="form">
                <div class="field">
                    <label for="lookup-service">Service pour ce nouveau passage</label>
                    <select id="lookup-service" wire:model="serviceId">
                        <option value="">Choisir un service</option>
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}">{{ $service->name }}</option>
                        @endforeach
                    </select>
                    @error('serviceId') <p class="field__error">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label for="lookup-reason">Motif de ce passage <span class="field__hint">(facultatif)</span></label>
                    <input id="lookup-reason" type="text" wire:model="reason"
                           placeholder="ex. retour pour douleurs abdominales, sans lien avec l'episode precedent">
                    @error('reason') <p class="field__error">{{ $message }}</p> @enderror
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn btn--primary">Nouvel episode</button>
                    <button type="button" class="btn btn--ghost" wire:click="cancel">Ce n'est pas ce patient</button>
                </div>
            </form>
        </div>
    @endif
</x-card>
