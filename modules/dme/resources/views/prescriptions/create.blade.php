@extends('dme::layouts.app')

@section('title', 'Nouvelle ordonnance')

@section('content')
    <x-dme::page-header title="Nouvelle ordonnance"
                   :subtitle="$patient->fullName().' - '.$patient->patient_number"
                   :breadcrumbs="[
                       'Patients' => route('dme.patients.index'),
                       $patient->fullName() => route('dme.patients.show', $patient),
                       'Nouvelle ordonnance' => null,
                   ]"/>

    {{-- Les allergies connues restent visibles pendant toute la saisie (§22) --}}
    <section class="k-card mb-4">
        <div class="p-4 sm:p-5">
            <x-dme::patient-header :patient="$patient" compact/>
            @if ($patient->criticalAllergies()->isNotEmpty())
                <div class="mt-4"><x-dme::medical-alerts :patient="$patient"/></div>
                <p class="mt-2 text-xs text-ink-500">
                    L'application confronte automatiquement les médicaments saisis aux allergies documentées
                    et vous avertit avant validation. Elle ne retire jamais une ligne : la décision vous appartient.
                </p>
            @endif
        </div>
    </section>

    <form action="{{ route('dme.prescriptions.store', $patient) }}" method="POST" novalidate>
        @csrf
        @if ($consultation)
            <input type="hidden" name="consultation_id" value="{{ $consultation->id }}">
        @endif

        <fieldset class="k-fieldset mb-4">
            <legend class="k-fieldset-legend">
                <x-dme::icon name="document" class="h-4.5 w-4.5 text-clinic-600"/> En-tête
            </legend>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <span class="k-label">Prescripteur</span>
                    <p class="rounded-lg bg-ink-50 px-3 py-2 text-sm text-ink-700">{{ auth()->user()->displayName() }}</p>
                </div>
                <div>
                    <span class="k-label">Établissement</span>
                    <p class="rounded-lg bg-ink-50 px-3 py-2 text-sm text-ink-700">{{ \Keneya\Dme\Dme::facility()['name'] }}</p>
                </div>
                <div>
                    <label for="issued_on" class="k-label">Date <span class="text-red-600" aria-hidden="true">*</span></label>
                    <input id="issued_on" name="issued_on" type="date" required
                           value="{{ old('issued_on', now()->toDateString()) }}" class="k-input">
                    <x-dme::field-error name="issued_on"/>
                </div>
                <div>
                    <label for="valid_until" class="k-label">Valable jusqu'au</label>
                    <input id="valid_until" name="valid_until" type="date"
                           value="{{ old('valid_until', now()->addMonths(3)->toDateString()) }}" class="k-input">
                    <x-dme::field-error name="valid_until"/>
                </div>
            </div>
            @if ($consultation)
                <p class="k-hint">
                    Rattachée à la consultation
                    <span class="font-mono">{{ $consultation->consultation_number }}</span>
                    du {{ $consultation->started_at->translatedFormat('d M Y') }}.
                </p>
            @endif
        </fieldset>

        {{-- Constructeur d'ordonnance (§22) --}}
        <fieldset class="k-fieldset mb-4"
                  x-data="prescriptionBuilder({{ Illuminate\Support\Js::from(old('items', [])) }})">
            <legend class="k-fieldset-legend">
                <x-dme::icon name="pill" class="h-4.5 w-4.5 text-clinic-600"/> Médicaments
            </legend>

            @if ($usualMedications->isNotEmpty())
                <div class="rounded-lg bg-clinic-50 p-3">
                    <p class="text-xs font-semibold text-clinic-800">Traitements habituels du patient</p>
                    <ul class="mt-1 space-y-0.5 text-xs text-clinic-900">
                        @foreach ($usualMedications as $medication)
                            <li>{{ $medication->name }} {{ $medication->dosage }} - {{ $medication->frequency }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <template x-for="(item, index) in items" :key="index">
                <div class="rounded-lg border border-ink-200 p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-xs font-semibold text-ink-500">
                            Ligne <span x-text="index + 1"></span>
                        </span>
                        <div class="flex gap-1">
                            <button type="button" @click="duplicate(index)" class="k-btn-ghost k-btn-sm"
                                    aria-label="Dupliquer cette ligne">
                                <x-dme::icon name="copy" class="h-4 w-4"/>
                            </button>
                            <button type="button" @click="remove(index)" class="k-btn-ghost k-btn-sm text-red-600"
                                    aria-label="Retirer cette ligne">
                                <x-dme::icon name="trash" class="h-4 w-4"/>
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="lg:col-span-2">
                            <label class="k-label" :for="'med_' + index">
                                Médicament <span class="text-red-600" aria-hidden="true">*</span>
                            </label>
                            <input :id="'med_' + index" :name="`items[${index}][medication_name]`"
                                   x-model="item.medication_name" type="text" maxlength="200" class="k-input"
                                   placeholder="Dénomination commune internationale de préférence">
                        </div>
                        <div>
                            <label class="k-label" :for="'dosage_' + index">Dosage</label>
                            <input :id="'dosage_' + index" :name="`items[${index}][dosage]`" x-model="item.dosage"
                                   type="text" maxlength="100" class="k-input" placeholder="500 mg">
                        </div>
                        <div>
                            <label class="k-label" :for="'form_' + index">Forme</label>
                            <input :id="'form_' + index" :name="`items[${index}][form]`" x-model="item.form"
                                   type="text" maxlength="100" class="k-input" placeholder="Comprimé">
                        </div>
                        <div>
                            <label class="k-label" :for="'route_' + index">Voie</label>
                            <input :id="'route_' + index" :name="`items[${index}][route]`" x-model="item.route"
                                   type="text" maxlength="50" class="k-input" placeholder="Orale">
                        </div>
                        <div>
                            <label class="k-label" :for="'freq_' + index">Fréquence</label>
                            <input :id="'freq_' + index" :name="`items[${index}][frequency]`" x-model="item.frequency"
                                   type="text" maxlength="100" class="k-input" placeholder="2 fois par jour">
                        </div>
                        <div>
                            <label class="k-label" :for="'duration_' + index">Durée</label>
                            <input :id="'duration_' + index" :name="`items[${index}][duration]`" x-model="item.duration"
                                   type="text" maxlength="100" class="k-input" placeholder="7 jours">
                        </div>
                        <div>
                            <label class="k-label" :for="'qty_' + index">Quantité</label>
                            <input :id="'qty_' + index" :name="`items[${index}][quantity]`" x-model="item.quantity"
                                   type="text" maxlength="100" class="k-input" placeholder="14 comprimés">
                        </div>
                        <div class="sm:col-span-2 lg:col-span-4">
                            <label class="k-label" :for="'instructions_' + index">Instructions au patient</label>
                            <input :id="'instructions_' + index" :name="`items[${index}][instructions]`"
                                   x-model="item.instructions" type="text" maxlength="500" class="k-input"
                                   placeholder="À prendre au milieu des repas.">
                        </div>
                    </div>
                </div>
            </template>

            <button type="button" @click="add()" class="k-btn-secondary">
                <x-dme::icon name="plus" class="h-4 w-4"/> Ajouter un médicament
            </button>

            <x-dme::field-error name="items"/>
        </fieldset>

        <fieldset class="k-fieldset mb-4">
            <legend class="k-fieldset-legend">Instructions générales</legend>
            <textarea name="instructions" rows="2" maxlength="2000" class="k-textarea"
                      placeholder="Conseils applicables à l'ensemble de l'ordonnance.">{{ old('instructions') }}</textarea>
        </fieldset>

        <div class="flex flex-wrap items-center gap-2">
            <button type="submit" class="k-btn-primary">Enregistrer l'ordonnance</button>
            <a href="{{ route('dme.patients.show', $patient) }}" class="k-btn-ghost">Annuler</a>
        </div>
    </form>
@endsection
