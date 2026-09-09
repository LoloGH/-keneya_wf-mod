<x-card title="Antecedents et allergies" icon="document">
    <p class="hint">
        Ces informations appartiennent au patient, pas a son passage du jour : elles
        restent au <strong>dossier medical</strong> et servent a toutes ses venues.
    </p>

    @if ($visits->isEmpty())
        <p class="empty">Appelez un patient pour consigner ses antecedents ou ses allergies.</p>
    @else
        <div class="field">
            <label for="mb-visit">Patient</label>
            <select id="mb-visit" wire:model.live="visitId">
                <option value="">Choisir un patient appele</option>
                @foreach ($visits as $visit)
                    <option value="{{ $visit->id }}">
                        n° {{ $visit->token }} - {{ $visit->patient->name }} ({{ $visit->patient->patient_code }})
                    </option>
                @endforeach
            </select>
            @error('visitId') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        @if ($visitId)

            {{-- ------------------------------------------------ Allergies --}}
            {{-- Placees en premier, et ce n'est pas un detail de mise en page :
                 c'est la seule donnee de cet ecran que le module relit pour
                 alerter au moment de prescrire. --}}
            @if ($peutAllergies)
                <fieldset class="formset">
                    <legend>Allergies connues</legend>

                    @if ($allergies->isEmpty())
                        <p class="empty">Aucune allergie consignee a ce jour.</p>
                    @else
                        <ul class="my-patients">
                            @foreach ($allergies as $allergie)
                                <li class="my-patients__item">
                                    <strong>{{ $allergie->allergen }}</strong>
                                    <span class="badge badge--{{ $allergie->status === 'active' ? 'called' : 'closed' }}">
                                        {{ $severities[$allergie->severity] ?? $allergie->severity }}
                                    </span>
                                    @if ($allergie->status !== 'active')
                                        <span class="hint">{{ $allergie->status === 'refuted' ? 'Refutee' : 'Resolue' }}</span>
                                    @endif
                                    @if ($allergie->reaction)
                                        <span>- {{ $allergie->reaction }}</span>
                                    @endif

                                    @if ($allergie->status === 'active')
                                        <div class="btn-row">
                                            <button type="button" class="btn btn--ghost"
                                                    wire:click="setAllergyStatus({{ $allergie->id }}, 'resolved')">
                                                Marquer resolue
                                            </button>
                                            <button type="button" class="btn btn--ghost"
                                                    wire:click="setAllergyStatus({{ $allergie->id }}, 'refuted')">
                                                Refuter
                                            </button>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <form wire:submit="saveAllergy" class="form">
                        <div class="form form--inline-wrap">
                            <div class="field">
                                <label for="mb-allergen">Allergene</label>
                                <input id="mb-allergen" type="text" wire:model="allergen"
                                       placeholder="Penicilline, arachide...">
                                @error('allergen') <p class="field__error">{{ $message }}</p> @enderror
                            </div>

                            <div class="field">
                                <label for="mb-allergen-type">Type <span class="field__hint">(facultatif)</span></label>
                                <select id="mb-allergen-type" wire:model="allergenType">
                                    <option value="">Non precise</option>
                                    @foreach ($allergenTypes as $valeur => $libelle)
                                        <option value="{{ $valeur }}">{{ $libelle }}</option>
                                    @endforeach
                                </select>
                                @error('allergenType') <p class="field__error">{{ $message }}</p> @enderror
                            </div>

                            <div class="field">
                                <label for="mb-severity">Severite</label>
                                <select id="mb-severity" wire:model="severity">
                                    @foreach ($severities as $valeur => $libelle)
                                        <option value="{{ $valeur }}">{{ $libelle }}</option>
                                    @endforeach
                                </select>
                                @error('severity') <p class="field__error">{{ $message }}</p> @enderror
                            </div>

                            <div class="field">
                                <label for="mb-reaction">Reaction <span class="field__hint">(facultatif)</span></label>
                                <input id="mb-reaction" type="text" wire:model="reaction"
                                       placeholder="Urticaire, oedeme, choc...">
                                @error('reaction') <p class="field__error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="field">
                            <label for="mb-allergy-comment">Commentaire <span class="field__hint">(facultatif)</span></label>
                            <textarea id="mb-allergy-comment" rows="2" wire:model="allergyComment"></textarea>
                            @error('allergyComment') <p class="field__error">{{ $message }}</p> @enderror
                        </div>

                        <div class="btn-row">
                            <button type="submit" class="btn btn--primary"
                                    wire:loading.attr="disabled" wire:target="saveAllergy">
                                Consigner l'allergie
                            </button>
                        </div>
                    </form>
                </fieldset>
            @endif

            {{-- ---------------------------------------------- Antecedents --}}
            @if ($peutAntecedents)
                <fieldset class="formset">
                    <legend>Antecedents</legend>

                    @if ($histories->isEmpty())
                        <p class="empty">Aucun antecedent consigne a ce jour.</p>
                    @else
                        <ul class="my-patients">
                            @foreach ($histories as $antecedent)
                                <li class="my-patients__item">
                                    <span class="badge">{{ $categories[$antecedent->category] ?? $antecedent->category }}</span>
                                    <strong>{{ $antecedent->label }}</strong>
                                    @if ($antecedent->year) <time>{{ $antecedent->year }}</time> @endif
                                    @if ($antecedent->relative) <span>- {{ $antecedent->relative }}</span> @endif
                                    @if ($antecedent->facility) <span>- {{ $antecedent->facility }}</span> @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <form wire:submit="saveHistory" class="form">
                        <div class="form form--inline-wrap">
                            <div class="field">
                                <label for="mb-category">Categorie</label>
                                <select id="mb-category" wire:model.live="category">
                                    @foreach ($categories as $valeur => $libelle)
                                        <option value="{{ $valeur }}">{{ $libelle }}</option>
                                    @endforeach
                                </select>
                                @error('category') <p class="field__error">{{ $message }}</p> @enderror
                            </div>

                            <div class="field field--wide">
                                <label for="mb-label">Antecedent</label>
                                <input id="mb-label" type="text" wire:model="label"
                                       placeholder="Hypertension arterielle, appendicectomie...">
                                @error('label') <p class="field__error">{{ $message }}</p> @enderror
                            </div>

                            <div class="field">
                                <label for="mb-year">Annee <span class="field__hint">(facultatif)</span></label>
                                <input id="mb-year" type="number" wire:model="year" placeholder="2019">
                                @error('year') <p class="field__error">{{ $message }}</p> @enderror
                            </div>

                            {{-- Ces deux champs n'apparaissent que pour la
                                 categorie qui leur donne un sens. --}}
                            @if ($category === 'family')
                                <div class="field">
                                    <label for="mb-relative">Lien de parente</label>
                                    <input id="mb-relative" type="text" wire:model="relative" placeholder="Mere, frere...">
                                    @error('relative') <p class="field__error">{{ $message }}</p> @enderror
                                </div>
                            @endif

                            @if ($category === 'surgical')
                                <div class="field">
                                    <label for="mb-facility">Etablissement</label>
                                    <input id="mb-facility" type="text" wire:model="facility">
                                    @error('facility') <p class="field__error">{{ $message }}</p> @enderror
                                </div>
                            @endif
                        </div>

                        <div class="field">
                            <label for="mb-comment">Precisions <span class="field__hint">(facultatif)</span></label>
                            <textarea id="mb-comment" rows="2" wire:model="comment"></textarea>
                            @error('comment') <p class="field__error">{{ $message }}</p> @enderror
                        </div>

                        <div class="btn-row">
                            <button type="submit" class="btn btn--primary"
                                    wire:loading.attr="disabled" wire:target="saveHistory">
                                Consigner l'antecedent
                            </button>
                        </div>
                    </form>
                </fieldset>
            @endif
        @endif
    @endif
</x-card>
