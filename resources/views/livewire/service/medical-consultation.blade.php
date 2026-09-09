<x-card title="Consultation" icon="soins">
    <p class="hint">
        Ce que vous saisissez ici est enregistre dans le <strong>dossier medical</strong> du
        patient, pas dans son dossier de passage. Seul le motif est obligatoire.
    </p>

    @if ($visits->isEmpty())
        <p class="empty">Appelez un patient pour rediger sa consultation.</p>
    @else
        <div class="field">
            <label for="mc-visit">Patient</label>
            <select id="mc-visit" wire:model.live="visitId">
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
            <form wire:submit="save" class="form">

                {{-- Contexte de l'acte --}}
                <div class="form form--inline-wrap">
                    <div class="field">
                        <label for="mc-date">Date et heure</label>
                        <input id="mc-date" type="datetime-local" wire:model="startedAt">
                        @error('startedAt') <p class="field__error">{{ $message }}</p> @enderror
                    </div>

                    <div class="field">
                        <label for="mc-type">Type de consultation</label>
                        <select id="mc-type" wire:model="type">
                            @foreach ($types as $valeur => $libelle)
                                <option value="{{ $valeur }}">{{ $libelle }}</option>
                            @endforeach
                        </select>
                        @error('type') <p class="field__error">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Motif et histoire de la maladie --}}
                <div class="field">
                    <label for="mc-reason">Motif de consultation</label>
                    <textarea id="mc-reason" rows="2" wire:model="reason"
                              placeholder="Ce qui amene le patient aujourd'hui."></textarea>
                    @error('reason') <p class="field__error">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label for="mc-history">
                        Histoire de la maladie <span class="field__hint">(facultatif)</span>
                    </label>
                    <textarea id="mc-history" rows="3" wire:model="historyOfIllness"
                              placeholder="Depuis quand, comment cela a evolue, ce qui a deja ete tente."></textarea>
                    @error('historyOfIllness') <p class="field__error">{{ $message }}</p> @enderror
                </div>

                {{-- Constantes. Toutes facultatives : une consultation sans
                     constante reste une consultation. --}}
                <fieldset class="formset">
                    <legend>Constantes <span class="field__hint">(facultatif)</span></legend>

                    <div class="form form--inline-wrap">
                        <div class="field">
                            <label for="mc-temp">Temperature (°C)</label>
                            <input id="mc-temp" type="number" step="0.1" wire:model="vitals.temperature">
                            @error('vitals.temperature') <p class="field__error">{{ $message }}</p> @enderror
                        </div>
                        <div class="field">
                            <label for="mc-sys">Tension systolique (mmHg)</label>
                            <input id="mc-sys" type="number" wire:model="vitals.systolic">
                            @error('vitals.systolic') <p class="field__error">{{ $message }}</p> @enderror
                        </div>
                        <div class="field">
                            <label for="mc-dia">Tension diastolique (mmHg)</label>
                            <input id="mc-dia" type="number" wire:model="vitals.diastolic">
                            @error('vitals.diastolic') <p class="field__error">{{ $message }}</p> @enderror
                        </div>
                        <div class="field">
                            <label for="mc-fc">Pouls (/min)</label>
                            <input id="mc-fc" type="number" wire:model="vitals.heart_rate">
                            @error('vitals.heart_rate') <p class="field__error">{{ $message }}</p> @enderror
                        </div>
                        <div class="field">
                            <label for="mc-fr">Frequence respiratoire (/min)</label>
                            <input id="mc-fr" type="number" wire:model="vitals.respiratory_rate">
                            @error('vitals.respiratory_rate') <p class="field__error">{{ $message }}</p> @enderror
                        </div>
                        <div class="field">
                            <label for="mc-spo2">Saturation (%)</label>
                            <input id="mc-spo2" type="number" wire:model="vitals.oxygen_saturation">
                            @error('vitals.oxygen_saturation') <p class="field__error">{{ $message }}</p> @enderror
                        </div>
                        <div class="field">
                            <label for="mc-poids">Poids (kg)</label>
                            <input id="mc-poids" type="number" step="0.1" wire:model="vitals.weight">
                            @error('vitals.weight') <p class="field__error">{{ $message }}</p> @enderror
                        </div>
                        <div class="field">
                            <label for="mc-taille">Taille (cm)</label>
                            <input id="mc-taille" type="number" step="0.1" wire:model="vitals.height">
                            @error('vitals.height') <p class="field__error">{{ $message }}</p> @enderror
                        </div>
                        <div class="field">
                            <label for="mc-glycemie">Glycemie (g/L)</label>
                            <input id="mc-glycemie" type="number" step="0.01" wire:model="vitals.glycemia">
                            @error('vitals.glycemia') <p class="field__error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </fieldset>

                {{-- Examen clinique par appareil. La liste vient du module :
                     c'est lui qui tient le vocabulaire du dossier. --}}
                <fieldset class="formset">
                    <legend>Examen clinique <span class="field__hint">(remplissez ce que vous avez examine)</span></legend>

                    @foreach ($systems as $cle => $libelle)
                        <div class="field">
                            <label for="mc-exam-{{ $cle }}">{{ $libelle }}</label>
                            <textarea id="mc-exam-{{ $cle }}" rows="2" wire:model="exam.{{ $cle }}"></textarea>
                            @error('exam.'.$cle) <p class="field__error">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </fieldset>

                {{-- Diagnostics --}}
                <fieldset class="formset">
                    <legend>Diagnostics <span class="field__hint">(facultatif)</span></legend>

                    @foreach ($diagnoses as $index => $ligne)
                        <div class="form form--inline-wrap">
                            <div class="field">
                                <label for="mc-diag-{{ $index }}">Diagnostic</label>
                                <input id="mc-diag-{{ $index }}" type="text"
                                       wire:model="diagnoses.{{ $index }}.label"
                                       placeholder="Paludisme simple">
                                @error('diagnoses.'.$index.'.label') <p class="field__error">{{ $message }}</p> @enderror
                            </div>

                            <div class="field">
                                <label for="mc-diag-code-{{ $index }}">
                                    Code CIM-10 <span class="field__hint">(facultatif)</span>
                                </label>
                                <input id="mc-diag-code-{{ $index }}" type="text"
                                       wire:model="diagnoses.{{ $index }}.code" placeholder="B54">
                                @error('diagnoses.'.$index.'.code') <p class="field__error">{{ $message }}</p> @enderror
                            </div>

                            <div class="field">
                                <label for="mc-diag-type-{{ $index }}">Rang</label>
                                <select id="mc-diag-type-{{ $index }}" wire:model="diagnoses.{{ $index }}.type">
                                    <option value="primary">Principal</option>
                                    <option value="secondary">Secondaire</option>
                                    <option value="differential">Differentiel</option>
                                </select>
                            </div>

                            <div class="field">
                                <label for="mc-diag-statut-{{ $index }}">Statut</label>
                                <select id="mc-diag-statut-{{ $index }}" wire:model="diagnoses.{{ $index }}.status">
                                    @foreach ($diagnosisStatuses as $valeur => $libelle)
                                        <option value="{{ $valeur }}">{{ $libelle }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="field field--inline">
                                <button type="button" class="btn btn--ghost"
                                        wire:click="removeDiagnosis({{ $index }})">
                                    Retirer
                                </button>
                            </div>
                        </div>
                    @endforeach

                    @if (count($diagnoses) < \App\Livewire\Service\MedicalConsultation::MAX_DIAGNOSTICS)
                        <button type="button" class="btn btn--secondary" wire:click="addDiagnosis">
                            Ajouter un diagnostic
                        </button>
                    @endif
                </fieldset>

                {{-- Conduite a tenir --}}
                <div class="field">
                    <label for="mc-plan">
                        Conduite a tenir <span class="field__hint">(facultatif)</span>
                    </label>
                    <textarea id="mc-plan" rows="3" wire:model="treatmentPlan"
                              placeholder="Traitement, examens demandes, orientation."></textarea>
                    @error('treatmentPlan') <p class="field__error">{{ $message }}</p> @enderror
                </div>

                <div class="form form--inline-wrap">
                    <div class="field">
                        <label for="mc-suivi">Suivi <span class="field__hint">(facultatif)</span></label>
                        <textarea id="mc-suivi" rows="2" wire:model="followUp"
                                  placeholder="Quand revoir le patient, et pourquoi."></textarea>
                        @error('followUp') <p class="field__error">{{ $message }}</p> @enderror
                    </div>

                    <div class="field">
                        <label for="mc-reco">Recommandations <span class="field__hint">(facultatif)</span></label>
                        <textarea id="mc-reco" rows="2" wire:model="recommendations"
                                  placeholder="Ce que le patient doit faire ou eviter."></textarea>
                        @error('recommendations') <p class="field__error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn btn--primary"
                            wire:loading.attr="disabled" wire:target="save">
                        Enregistrer au dossier medical
                    </button>
                    <p class="hint" wire:loading wire:target="save">Enregistrement en cours...</p>
                </div>
            </form>
        @endif
    @endif
</x-card>
