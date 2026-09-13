@extends('dme::layouts.app')

@section('title', 'Nouvelle demande d\'imagerie')

@section('content')
    <x-dme::page-header title="Nouvelle demande d'imagerie"
                   :subtitle="$patient->fullName().' - '.$patient->patient_number"
                   :breadcrumbs="[
                       'Patients' => route('dme.patients.index'),
                       $patient->fullName() => route('dme.patients.show', $patient),
                       'Demande d\'imagerie' => null,
                   ]"/>

    <form action="{{ route('dme.imaging.store', $patient) }}" method="POST" novalidate>
        @csrf
        @if ($consultationId)
            <input type="hidden" name="consultation_id" value="{{ $consultationId }}">
        @endif

        <fieldset class="k-fieldset mb-4">
            <legend class="k-fieldset-legend">
                <x-dme::icon name="scan" class="h-4.5 w-4.5 text-clinic-600"/> Examen demandé
            </legend>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="modality" class="k-label">Type d'examen <span class="text-red-600" aria-hidden="true">*</span></label>
                    <select id="modality" name="modality" required class="k-select">
                        @foreach (\Keneya\Dme\Models\ImagingOrder::MODALITIES as $value => $label)
                            <option value="{{ $value }}" @selected(old('modality') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-dme::field-error name="modality"/>
                </div>
                <div>
                    <label for="body_site" class="k-label">Région explorée</label>
                    <input id="body_site" name="body_site" type="text" maxlength="150" class="k-input"
                           value="{{ old('body_site') }}" placeholder="Abdomen complet, thorax, genou droit...">
                </div>
                <div>
                    <label for="priority" class="k-label">Urgence <span class="text-red-600" aria-hidden="true">*</span></label>
                    <select id="priority" name="priority" required class="k-select">
                        <option value="routine">Normale</option>
                        <option value="urgent">Urgente</option>
                        <option value="vital">Vitale</option>
                    </select>
                </div>
                <div>
                    <label for="requested_at" class="k-label">Date de demande <span class="text-red-600" aria-hidden="true">*</span></label>
                    <input id="requested_at" name="requested_at" type="datetime-local" required
                           value="{{ old('requested_at', now()->format('Y-m-d\TH:i')) }}" class="k-input">
                </div>
                <div>
                    <label for="scheduled_for" class="k-label">Programmé pour</label>
                    <input id="scheduled_for" name="scheduled_for" type="datetime-local"
                           value="{{ old('scheduled_for') }}" class="k-input">
                    <x-dme::field-error name="scheduled_for"/>
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <label for="indication" class="k-label">Indication clinique</label>
                    <textarea id="indication" name="indication" rows="3" maxlength="1000" class="k-textarea"
                              placeholder="Question posée au radiologue.">{{ old('indication') }}</textarea>
                </div>
            </div>
            <p class="k-hint">
                Un numéro d'accession est attribué automatiquement. Il servira de clé de rapprochement
                lors d'une future intégration DICOM/PACS ; cette version ne stocke que les métadonnées
                et les documents associés.
            </p>
        </fieldset>

        <div class="flex flex-wrap items-center gap-2">
            <button type="submit" class="k-btn-primary">Créer la demande</button>
            <a href="{{ route('dme.patients.show', $patient) }}" class="k-btn-ghost">Annuler</a>
        </div>
    </form>
@endsection
