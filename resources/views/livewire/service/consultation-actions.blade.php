<x-card title="Fin de consultation" icon="soins">

    @if ($visits->isEmpty())
        <p class="empty">Appelez un patient pour encaisser un acte, etablir une ordonnance ou fixer un rendez-vous.</p>
    @else
        <div class="field">
            <label for="consultation-visit">Patient</label>
            <select id="consultation-visit" wire:model.live="visitId">
                <option value="">Choisir un patient appele</option>
                @foreach ($visits as $visit)
                    <option value="{{ $visit->id }}">
                        n° {{ $visit->token }} - {{ $visit->patient->name }} ({{ $visit->patient->patient_code }})
                    </option>
                @endforeach
            </select>
            @error('visitId') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        {{-- L'onglet « Caisse Services » a disparu : depuis la v3.2 point 6,
             un encaissement se fait a la caisse et nulle part ailleurs. Le
             composant ne portait deja plus `recordPayment`, un test le
             verifie, mais le bouton et son formulaire etaient restes ici,
             sans methode derriere. --}}
        <div class="tabs" role="tablist">
            <button type="button" role="tab" class="tabs__tab @if ($tab === 'ordonnance') tabs__tab--active @endif"
                    wire:click="selectTab('ordonnance')">Ordonnance</button>
            <button type="button" role="tab" class="tabs__tab @if ($tab === 'conclusion') tabs__tab--active @endif"
                    wire:click="selectTab('conclusion')">Pathologie</button>
            <button type="button" role="tab" class="tabs__tab @if ($tab === 'rendez-vous') tabs__tab--active @endif"
                    wire:click="selectTab('rendez-vous')">Rendez-vous</button>
        </div>

        @if ($tab === 'conclusion')
            {{-- La conclusion ne s'ecrit plus ici (v3.3.1) : elle est passee au
                 dossier medical, avec le motif, les constantes, l'examen et
                 les diagnostics. Deux champs pour un meme geste etaient
                 surtout une occasion de se tromper de place, et ce qui
                 s'ecrivait ici n'atteignait jamais le dossier. --}}
            <p class="notice">
                La conclusion de la consultation s'ecrit desormais dans
                <strong>Dossier medical &rsaquo; Consultation</strong>, avec le motif,
                les constantes et les diagnostics. Elle y reste au dossier du patient.
            </p>

            {{-- La pathologie, elle, reste ici : elle ne sert pas au soin mais
                 au fonctionnement de l'etablissement, s'adresser plus tard a un
                 groupe de patients par SMS. Facultative, et elle ne conditionne
                 rien (v3.2.9, point 1). --}}
            <form wire:submit="recordPathology" class="form">
                <div class="field">
                    <label for="consultation-pathologie">
                        Pathologie du passage <span class="field__hint">(facultatif)</span>
                    </label>
                    <select id="consultation-pathologie" wire:model="pathologyId">
                        <option value="">Non precisee</option>
                        @foreach ($pathologies as $pathologie)
                            <option value="{{ $pathologie->id }}">{{ $pathologie->name }}</option>
                        @endforeach
                    </select>
                    <p class="hint">
                        Sert a s'adresser plus tard a ce groupe de patients. Sans effet sur le soin.
                    </p>
                    @error('pathologyId') <p class="field__error">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="btn btn--primary" wire:loading.attr="disabled">
                    Noter la pathologie
                </button>
            </form>

        @elseif ($tab === 'ordonnance')
            {{-- L'ordonnance s'ecrit ligne par ligne, numerotee : c'est ainsi
                 qu'elle sera lue au comptoir de la pharmacie. --}}
            <form wire:submit="savePrescription" class="form">
                <ol class="ordo">
                    @foreach ($prescriptionLines as $index => $ligne)
                        <li class="ordo__line" wire:key="ordo-{{ $index }}">
                            <span class="ordo__rank" aria-hidden="true">{{ $index + 1 }}</span>

                            <div class="ordo__fields">
                                <div class="field">
                                    <label for="ordo-med-{{ $index }}">Medicament</label>
                                    <input id="ordo-med-{{ $index }}" type="text"
                                           wire:model="prescriptionLines.{{ $index }}.medicament"
                                           placeholder="Paracetamol 500 mg">
                                    @error("prescriptionLines.$index.medicament")
                                        <p class="field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="field">
                                    <label for="ordo-pos-{{ $index }}">Posologie</label>
                                    <input id="ordo-pos-{{ $index }}" type="text"
                                           wire:model="prescriptionLines.{{ $index }}.posologie"
                                           placeholder="1 comprime matin et soir">
                                    @error("prescriptionLines.$index.posologie")
                                        <p class="field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="field field--court">
                                    <label for="ordo-dur-{{ $index }}">Duree</label>
                                    <input id="ordo-dur-{{ $index }}" type="text"
                                           wire:model="prescriptionLines.{{ $index }}.duree"
                                           placeholder="5 jours">
                                    @error("prescriptionLines.$index.duree")
                                        <p class="field__error">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            {{-- Toujours presente, meme sur la premiere ligne :
                                 une action qui disparait laisse l'utilisateur
                                 devant une case vide. La derniere ligne se vide
                                 au lieu de disparaitre. --}}
                            <button type="button" class="btn btn--ghost btn--icon btn--icon-danger ordo__remove"
                                    wire:click="removePrescriptionLine({{ $index }})"
                                    aria-label="Retirer la ligne {{ $index + 1 }}"
                                    title="Retirer cette ligne">
                                <x-icon name="retirer" size="18" />
                            </button>
                        </li>
                    @endforeach
                </ol>

                @error('prescriptionLines') <p class="field__error">{{ $message }}</p> @enderror

                <div class="btn-row">
                    @if (count($prescriptionLines) < \App\Livewire\Service\ConsultationActions::MAX_LIGNES)
                        <button type="button" class="btn btn--ghost ordo__add" wire:click="addPrescriptionLine">
                            <x-icon name="ajouter" size="18" />
                            Ajouter une ligne
                        </button>
                    @endif

                    <button type="submit" class="btn btn--primary">Enregistrer l'ordonnance</button>
                </div>
            </form>
        @else
            <form wire:submit="saveAppointment" class="form">
                <div class="field">
                    <label for="appointment-at">Date et heure du prochain rendez-vous</label>
                    <input id="appointment-at" type="datetime-local" wire:model="appointmentAt">
                    @error('appointmentAt') <p class="field__error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn btn--primary">Fixer le rendez-vous</button>
            </form>
        @endif
    @endif
</x-card>
