@extends('dme::layouts.app')

@section('title', 'Nouvelle admission')

@section('content')
    <x-dme::page-header title="Nouvelle admission"
                   :subtitle="$patient->fullName().' - '.$patient->patient_number"
                   :breadcrumbs="[
                       'Patients' => route('dme.patients.index'),
                       $patient->fullName() => route('dme.patients.show', $patient),
                       'Admission' => null,
                   ]"/>

    <section class="k-card mb-4">
        <div class="p-4 sm:p-5">
            <x-dme::patient-header :patient="$patient" compact/>
            @if ($patient->criticalAllergies()->isNotEmpty() || $patient->activeConditions()->isNotEmpty())
                <div class="mt-4"><x-dme::medical-alerts :patient="$patient"/></div>
            @endif
        </div>
    </section>

    <form action="{{ route('dme.hospitalizations.store', $patient) }}" method="POST" novalidate>
        @csrf
        <fieldset class="k-fieldset mb-4">
            <legend class="k-fieldset-legend">
                <x-dme::icon name="bed" class="h-4.5 w-4.5 text-clinic-600"/> Admission
            </legend>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="admitted_at" class="k-label">Date et heure <span class="text-red-600" aria-hidden="true">*</span></label>
                    <input id="admitted_at" name="admitted_at" type="datetime-local" required
                           max="{{ now()->format('Y-m-d\TH:i') }}"
                           value="{{ old('admitted_at', now()->format('Y-m-d\TH:i')) }}" class="k-input">
                    <x-dme::field-error name="admitted_at"/>
                </div>
                <div>
                    <label for="service_id" class="k-label">Service</label>
                    <select id="service_id" name="service_id" class="k-select">
                        <option value="">Non précisé</option>
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}" @selected((string) old('service_id') === (string) $service->id)>
                                {{ $service->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="room" class="k-label">Chambre</label>
                    <input id="room" name="room" type="text" maxlength="50" value="{{ old('room') }}" class="k-input">
                </div>
                <div>
                    <label for="bed" class="k-label">Lit</label>
                    <input id="bed" name="bed" type="text" maxlength="50" value="{{ old('bed') }}" class="k-input">
                </div>
                <div class="sm:col-span-2">
                    <label for="admission_diagnosis" class="k-label">Diagnostic d'entrée</label>
                    <input id="admission_diagnosis" name="admission_diagnosis" type="text" maxlength="200"
                           value="{{ old('admission_diagnosis') }}" class="k-input">
                </div>
                <div class="sm:col-span-2 lg:col-span-4">
                    <label for="admission_reason" class="k-label">Motif d'admission <span class="text-red-600" aria-hidden="true">*</span></label>
                    <textarea id="admission_reason" name="admission_reason" rows="3" required maxlength="1000"
                              class="k-textarea">{{ old('admission_reason') }}</textarea>
                    <x-dme::field-error name="admission_reason"/>
                </div>
            </div>
        </fieldset>

        <div class="flex flex-wrap items-center gap-2">
            <button type="submit" class="k-btn-primary">Enregistrer l'admission</button>
            <a href="{{ route('dme.patients.show', $patient) }}" class="k-btn-ghost">Annuler</a>
        </div>
    </form>
@endsection
