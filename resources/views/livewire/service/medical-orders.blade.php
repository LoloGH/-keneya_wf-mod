<x-card title="Traitements, examens et documents" icon="soins">
    <p class="hint">
        Tout ce qui est saisi ici va au <strong>dossier medical</strong>. Une demande
        d'examen documente l'acte au dossier ; elle ne remplace pas le renvoi vers le
        service, qui reste ce qui fait circuler le patient.
    </p>

    @if ($visits->isEmpty())
        <p class="empty">Appelez un patient pour completer son dossier medical.</p>
    @else
        <div class="field">
            <label for="mo-visit">Patient</label>
            <select id="mo-visit" wire:model.live="visitId">
                <option value="">— Choisir un patient appele —</option>
                @foreach ($visits as $visit)
                    <option value="{{ $visit->id }}">
                        n° {{ $visit->token }} — {{ $visit->patient->name }} ({{ $visit->patient->patient_code }})
                    </option>
                @endforeach
            </select>
            @error('visitId') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        @if ($visitId)

            {{-- ------------------------------------- Traitements habituels --}}
            @if ($peutTraitements)
                <fieldset class="formset">
                    <legend>Traitements en cours</legend>

                    <p class="hint">
                        Ce que le patient prend deja, et non ce qu'on lui prescrit aujourd'hui :
                        l'ordonnance est ailleurs.
                    </p>

                    @if ($medications->isEmpty())
                        <p class="empty">Aucun traitement consigne a ce jour.</p>
                    @else
                        <ul class="my-patients">
                            @foreach ($medications as $traitement)
                                <li class="my-patients__item">
                                    <strong>{{ $traitement->name }}</strong>
                                    @if ($traitement->dosage) <span>{{ $traitement->dosage }}</span> @endif
                                    @if ($traitement->frequency) <span>— {{ $traitement->frequency }}</span> @endif
                                    <span class="badge badge--{{ $traitement->status === 'active' ? 'called' : 'closed' }}">
                                        {{ $medicationStatuses[$traitement->status] ?? $traitement->status }}
                                    </span>

                                    @if ($traitement->status === 'active')
                                        <div class="btn-row">
                                            <button type="button" class="btn btn--ghost"
                                                    wire:click="setMedicationStatus({{ $traitement->id }}, 'suspended')">
                                                Suspendre
                                            </button>
                                            <button type="button" class="btn btn--ghost"
                                                    wire:click="setMedicationStatus({{ $traitement->id }}, 'stopped')">
                                                Arreter
                                            </button>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <form wire:submit="saveMedication" class="form">
                        <div class="form form--inline-wrap">
                            <div class="field field--wide">
                                <label for="mo-med-name">Traitement</label>
                                <input id="mo-med-name" type="text" wire:model="medicationName"
                                       placeholder="Amlodipine">
                                @error('medicationName') <p class="field__error">{{ $message }}</p> @enderror
                            </div>
                            <div class="field">
                                <label for="mo-dosage">Dosage <span class="field__hint">(facultatif)</span></label>
                                <input id="mo-dosage" type="text" wire:model="dosage" placeholder="5 mg">
                                @error('dosage') <p class="field__error">{{ $message }}</p> @enderror
                            </div>
                            <div class="field">
                                <label for="mo-frequency">Frequence <span class="field__hint">(facultatif)</span></label>
                                <input id="mo-frequency" type="text" wire:model="frequency" placeholder="1 fois par jour">
                                @error('frequency') <p class="field__error">{{ $message }}</p> @enderror
                            </div>
                            <div class="field">
                                <label for="mo-route">Voie <span class="field__hint">(facultatif)</span></label>
                                <select id="mo-route" wire:model="route">
                                    <option value="">— Non precisee —</option>
                                    @foreach ($medicationRoutes as $valeur => $libelle)
                                        <option value="{{ $valeur }}">{{ $libelle }}</option>
                                    @endforeach
                                </select>
                                @error('route') <p class="field__error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="btn-row">
                            <button type="submit" class="btn btn--primary"
                                    wire:loading.attr="disabled" wire:target="saveMedication">
                                Consigner le traitement
                            </button>
                        </div>
                    </form>
                </fieldset>
            @endif

            {{-- ------------------------------------------------ Laboratoire --}}
            {{-- Les demandes se lisent ici, elles ne s'y posent plus (v3.3.1) :
                 demander un examen, c'est envoyer le patient le faire. Le
                 formulaire vit donc dans « Envoyer vers un service », ou il
                 s'affiche des que la destination realise des analyses. --}}
            @if ($peutLaboratoire)
                <fieldset class="formset">
                    <legend>Examens biologiques demandes</legend>

                    @if ($labOrders->isEmpty())
                        <p class="empty">Aucune demande d'analyses a ce dossier.</p>
                    @else
                        <ul class="my-patients">
                            @foreach ($labOrders as $demande)
                                <li class="my-patients__item">
                                    <span class="mono">{{ $demande->order_number }}</span>
                                    <span>{{ $demande->items->pluck('exam_name')->implode(', ') }}</span>
                                    <span class="badge">{{ $demande->statusLabel() }}</span>
                                    <time>{{ $demande->requested_at?->format('d/m/Y') }}</time>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <p class="hint">
                        Pour demander des analyses, envoyez le patient vers le
                        laboratoire depuis la file d'attente : la demande part
                        avec lui.
                    </p>
                </fieldset>
            @endif

            {{-- --------------------------------------------------- Imagerie --}}
            @if ($peutImagerie)
                <fieldset class="formset">
                    <legend>Examens d'imagerie demandes</legend>

                    @if ($imagingOrders->isEmpty())
                        <p class="empty">Aucune demande d'imagerie a ce dossier.</p>
                    @else
                        <ul class="my-patients">
                            @foreach ($imagingOrders as $demande)
                                <li class="my-patients__item">
                                    <span class="mono">{{ $demande->order_number }}</span>
                                    <span>{{ $modalities[$demande->modality] ?? $demande->modality }}</span>
                                    @if ($demande->body_site) <span>— {{ $demande->body_site }}</span> @endif
                                    <span class="badge">{{ $demande->statusLabel() }}</span>
                                    <time>{{ $demande->requested_at?->format('d/m/Y') }}</time>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <p class="hint">
                        Pour demander une imagerie, envoyez le patient vers le
                        plateau depuis la file d'attente : la demande part avec
                        lui.
                    </p>
                </fieldset>
            @endif

            {{-- -------------------------------------------------- Documents --}}
            @if ($peutDocuments)
                <fieldset class="formset">
                    <legend>Documents du dossier</legend>

                    @if ($documents->isNotEmpty())
                        <ul class="my-patients">
                            @foreach ($documents as $doc)
                                <li class="my-patients__item">
                                    <strong>{{ $doc->title }}</strong>
                                    <span class="badge">{{ $documentTypes[$doc->type] ?? $doc->type }}</span>
                                    <time>{{ $doc->created_at?->format('d/m/Y') }}</time>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <form wire:submit="saveDocument" class="form">
                        <div class="form form--inline-wrap">
                            <div class="field">
                                <label for="mo-doc-type">Type de document</label>
                                <select id="mo-doc-type" wire:model="documentType">
                                    @foreach ($documentTypes as $valeur => $libelle)
                                        <option value="{{ $valeur }}">{{ $libelle }}</option>
                                    @endforeach
                                </select>
                                @error('documentType') <p class="field__error">{{ $message }}</p> @enderror
                            </div>
                            <div class="field field--wide">
                                <label for="mo-doc-title">Titre <span class="field__hint">(facultatif)</span></label>
                                <input id="mo-doc-title" type="text" wire:model="documentTitle"
                                       placeholder="Compte rendu d'echographie du 08/09">
                                @error('documentTitle') <p class="field__error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="field">
                            <label for="mo-doc-file">Fichier</label>
                            <input id="mo-doc-file" type="file" wire:model="document">
                            @error('document') <p class="field__error">{{ $message }}</p> @enderror
                            <p class="hint" wire:loading wire:target="document">Televersement en cours…</p>
                        </div>

                        <div class="btn-row">
                            <button type="submit" class="btn btn--primary"
                                    wire:loading.attr="disabled" wire:target="saveDocument,document">
                                Verser au dossier
                            </button>
                        </div>
                    </form>
                </fieldset>
            @endif
        @endif
    @endif
</x-card>
