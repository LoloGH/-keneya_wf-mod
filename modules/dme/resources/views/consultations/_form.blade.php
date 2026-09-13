@php
    /** Formulaire de consultation (§19), partagé création / modification. */
    $consultation = $consultation ?? null;
    $notes = $consultation?->clinicalNotes->keyBy('system') ?? collect();
@endphp

{{-- Contexte patient rappelé en permanence, alertes comprises (§13) --}}
<section class="k-card mb-4">
    <div class="p-4 sm:p-5">
        <x-dme::patient-header :patient="$patient" compact/>
        @if ($patient->criticalAllergies()->isNotEmpty() || $patient->activeConditions()->isNotEmpty())
            <div class="mt-4"><x-dme::medical-alerts :patient="$patient"/></div>
        @endif
    </div>
</section>

<div class="space-y-4">

    {{-- Informations générales --}}
    <fieldset class="k-fieldset">
        <legend class="k-fieldset-legend">
            <x-dme::icon name="stethoscope" class="h-4.5 w-4.5 text-clinic-600"/> Informations
        </legend>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="started_at" class="k-label">Date et heure <span class="text-red-600" aria-hidden="true">*</span></label>
                <input id="started_at" name="started_at" type="datetime-local" required
                       max="{{ now()->format('Y-m-d\TH:i') }}"
                       value="{{ old('started_at', $consultation?->started_at->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i')) }}"
                       class="k-input">
                <x-dme::field-error name="started_at"/>
            </div>
            <div>
                <label for="type" class="k-label">Type <span class="text-red-600" aria-hidden="true">*</span></label>
                <select id="type" name="type" required class="k-select">
                    @foreach (\Keneya\Dme\Models\Consultation::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $consultation?->type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="service_id" class="k-label">Service</label>
                <select id="service_id" name="service_id" class="k-select">
                    <option value="">Non précisé</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}"
                            @selected((string) old('service_id', $consultation?->service_id) === (string) $service->id)>
                            {{ $service->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <span class="k-label">Médecin</span>
                <p class="rounded-lg bg-ink-50 px-3 py-2 text-sm text-ink-700">
                    {{ $consultation?->doctor?->displayName() ?? auth()->user()->displayName() }}
                </p>
            </div>
        </div>
    </fieldset>

    {{-- Motif et histoire de la maladie --}}
    <fieldset class="k-fieldset">
        <legend class="k-fieldset-legend">
            <x-dme::icon name="document" class="h-4.5 w-4.5 text-clinic-600"/> Motif et anamnèse
        </legend>
        <div>
            <label for="reason" class="k-label">Motif de consultation</label>
            <textarea id="reason" name="reason" rows="2" maxlength="1000" class="k-textarea"
                      placeholder="Ce qui amène le patient aujourd'hui.">{{ old('reason', $consultation?->reason) }}</textarea>
            <x-dme::field-error name="reason"/>
        </div>
        <div>
            <label for="history_of_illness" class="k-label">Histoire de la maladie</label>
            <textarea id="history_of_illness" name="history_of_illness" rows="5" maxlength="10000" class="k-textarea"
                      placeholder="Début, évolution, traitements déjà reçus, facteurs déclenchants...">{{ old('history_of_illness', $consultation?->history_of_illness) }}</textarea>
        </div>
    </fieldset>

    {{-- Constantes (§20) --}}
    <fieldset class="k-fieldset">
        <legend class="k-fieldset-legend">
            <x-dme::icon name="heart" class="h-4.5 w-4.5 text-red-600"/> Constantes vitales
        </legend>
        @if ($lastVitals)
            <p class="k-hint">
                Dernier relevé le {{ $lastVitals->measured_at->translatedFormat('d M Y à H:i') }} :
                TA {{ $lastVitals->bloodPressure() ?: '-' }} · poids {{ $lastVitals->weight ?: '-' }} kg.
                Les nouvelles valeurs créent un relevé supplémentaire ; aucune donnée antérieure n'est écrasée.
            </p>
        @endif
        <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ([
                'temperature' => ['Température (°C)', '0.1', '25', '45'],
                'systolic' => ['TA systolique (mmHg)', '1', '40', '300'],
                'diastolic' => ['TA diastolique (mmHg)', '1', '20', '200'],
                'heart_rate' => ['Pouls (bpm)', '1', '20', '250'],
                'respiratory_rate' => ['Fréq. respiratoire', '1', '5', '80'],
                'oxygen_saturation' => ['SpO₂ (%)', '1', '50', '100'],
                'weight' => ['Poids (kg)', '0.1', '0.5', '400'],
                'height' => ['Taille (cm)', '0.5', '20', '250'],
                'glycemia' => ['Glycémie (g/L)', '0.01', '0.1', '10'],
            ] as $field => [$label, $step, $min, $max])
                <div>
                    <label for="vitals_{{ $field }}" class="k-label">{{ $label }}</label>
                    <input id="vitals_{{ $field }}" name="vitals[{{ $field }}]" type="number"
                           step="{{ $step }}" min="{{ $min }}" max="{{ $max }}"
                           value="{{ old("vitals.$field") }}" class="k-input">
                    <x-dme::field-error :name="'vitals.'.$field"/>
                </div>
            @endforeach
            <div>
                <span class="k-label">IMC</span>
                <p class="rounded-lg bg-ink-50 px-3 py-2 text-sm text-ink-500">Calculé automatiquement</p>
            </div>
        </div>
    </fieldset>

    {{-- Examen clinique par appareil (§19) --}}
    <fieldset class="k-fieldset">
        <legend class="k-fieldset-legend">
            <x-dme::icon name="clipboard" class="h-4.5 w-4.5 text-clinic-600"/> Examen clinique
        </legend>
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach (\Keneya\Dme\Models\ClinicalNote::SYSTEMS as $system => $label)
                <div>
                    <label for="exam_{{ $system }}" class="k-label">{{ $label }}</label>
                    <textarea id="exam_{{ $system }}" name="exam[{{ $system }}]" rows="2" maxlength="5000"
                              class="k-textarea">{{ old("exam.$system", $notes[$system]->content ?? '') }}</textarea>
                </div>
            @endforeach
        </div>
    </fieldset>

    {{-- Diagnostics (§21) --}}
    <fieldset class="k-fieldset" x-data="diagnosisBuilder({{ Illuminate\Support\Js::from(
        old('diagnoses', $consultation?->diagnoses->map(fn ($d) => [
            'label' => $d->label, 'code' => $d->code, 'type' => $d->type,
            'status' => $d->status, 'comment' => $d->comment,
        ])->values()->all() ?? [])
    ) }})">
        <legend class="k-fieldset-legend">
            <x-dme::icon name="alert" class="h-4.5 w-4.5 text-clinic-600"/> Diagnostics
        </legend>
        <p class="k-hint">
            Le champ « code » accueille la CIM-10 (ex. I10 pour l'hypertension essentielle).
            La structure est en place ; le référentiel complet n'est pas embarqué dans cette version.
        </p>

        <template x-for="(row, index) in rows" :key="index">
            <div class="grid gap-3 rounded-lg border border-ink-200 p-3 sm:grid-cols-12">
                <div class="sm:col-span-5">
                    <label class="k-label" :for="'diag_label_' + index">Libellé</label>
                    <input :id="'diag_label_' + index" :name="`diagnoses[${index}][label]`" x-model="row.label"
                           type="text" maxlength="200" class="k-input" placeholder="Hypertension artérielle essentielle">
                </div>
                <div class="sm:col-span-2">
                    <label class="k-label" :for="'diag_code_' + index">Code CIM-10</label>
                    <input :id="'diag_code_' + index" :name="`diagnoses[${index}][code]`" x-model="row.code"
                           type="text" maxlength="20" class="k-input" placeholder="I10">
                </div>
                <div class="sm:col-span-2">
                    <label class="k-label" :for="'diag_type_' + index">Type</label>
                    <select :id="'diag_type_' + index" :name="`diagnoses[${index}][type]`" x-model="row.type" class="k-select">
                        <option value="primary">Principal</option>
                        <option value="secondary">Associé</option>
                        <option value="differential">Différentiel</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="k-label" :for="'diag_status_' + index">Statut</label>
                    <select :id="'diag_status_' + index" :name="`diagnoses[${index}][status]`" x-model="row.status" class="k-select">
                        @foreach (\Keneya\Dme\Models\Diagnosis::STATUSES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end sm:col-span-1">
                    <button type="button" @click="remove(index)" class="k-btn-ghost k-btn-sm w-full text-red-600"
                            aria-label="Retirer ce diagnostic">
                        <x-dme::icon name="trash" class="h-4 w-4"/>
                    </button>
                </div>
            </div>
        </template>

        <button type="button" @click="add()" class="k-btn-secondary k-btn-sm">
            <x-dme::icon name="plus" class="h-3.5 w-3.5"/> Ajouter un diagnostic
        </button>
    </fieldset>

    {{-- Plan de soins --}}
    <fieldset class="k-fieldset">
        <legend class="k-fieldset-legend">
            <x-dme::icon name="check" class="h-4.5 w-4.5 text-keneya-600"/> Traitement et plan de soins
        </legend>
        <div class="grid gap-4 lg:grid-cols-3">
            <div>
                <label for="treatment_plan" class="k-label">Traitement</label>
                <textarea id="treatment_plan" name="treatment_plan" rows="4" maxlength="10000" class="k-textarea"
                          placeholder="Molécules, posologies, durée.">{{ old('treatment_plan', $consultation?->treatment_plan) }}</textarea>
                <p class="k-hint">L'ordonnance formelle se crée ensuite via « Enregistrer et prescrire ».</p>
            </div>
            <div>
                <label for="follow_up" class="k-label">Examens et suivi</label>
                <textarea id="follow_up" name="follow_up" rows="4" maxlength="5000" class="k-textarea"
                          placeholder="Examens à demander, prochaine échéance.">{{ old('follow_up', $consultation?->follow_up) }}</textarea>
            </div>
            <div>
                <label for="recommendations" class="k-label">Recommandations au patient</label>
                <textarea id="recommendations" name="recommendations" rows="4" maxlength="5000" class="k-textarea"
                          placeholder="Hygiène de vie, signes d'alerte devant motiver une consultation.">{{ old('recommendations', $consultation?->recommendations) }}</textarea>
            </div>
        </div>
    </fieldset>
</div>
