@extends('dme::layouts.app')

@section('title', 'Nouvelle demande d\'analyse')

@section('content')
    <x-dme::page-header title="Nouvelle demande d'analyse"
                   :subtitle="$patient->fullName().' - '.$patient->patient_number"
                   :breadcrumbs="[
                       'Patients' => route('dme.patients.index'),
                       $patient->fullName() => route('dme.patients.show', $patient),
                       'Demande d\'analyse' => null,
                   ]"/>

    <form action="{{ route('dme.laboratory.store', $patient) }}" method="POST" novalidate>
        @csrf
        @if ($consultationId)
            <input type="hidden" name="consultation_id" value="{{ $consultationId }}">
        @endif

        <fieldset class="k-fieldset mb-4">
            <legend class="k-fieldset-legend">
                <x-dme::icon name="flask" class="h-4.5 w-4.5 text-clinic-600"/> Demande
            </legend>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="requested_at" class="k-label">Date <span class="text-red-600" aria-hidden="true">*</span></label>
                    <input id="requested_at" name="requested_at" type="datetime-local" required
                           value="{{ old('requested_at', now()->format('Y-m-d\TH:i')) }}" class="k-input">
                    <x-dme::field-error name="requested_at"/>
                </div>
                <div>
                    <label for="priority" class="k-label">Urgence <span class="text-red-600" aria-hidden="true">*</span></label>
                    <select id="priority" name="priority" required class="k-select">
                        @foreach (\Keneya\Dme\Models\LabOrder::PRIORITIES as $value => $label)
                            <option value="{{ $value }}" @selected(old('priority') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <span class="k-label">Prescripteur</span>
                    <p class="rounded-lg bg-ink-50 px-3 py-2 text-sm text-ink-700">{{ auth()->user()->displayName() }}</p>
                </div>
                <div class="sm:col-span-3">
                    <label for="indication" class="k-label">Indication clinique</label>
                    <textarea id="indication" name="indication" rows="2" maxlength="1000" class="k-textarea"
                              placeholder="Contexte justifiant la demande, il oriente l'interprétation du biologiste.">{{ old('indication') }}</textarea>
                </div>
            </div>
        </fieldset>

        <fieldset class="k-fieldset mb-4">
            <legend class="k-fieldset-legend">
                <x-dme::icon name="check" class="h-4.5 w-4.5 text-clinic-600"/> Examens demandés
                <span class="text-red-600" aria-hidden="true">*</span>
            </legend>
            <x-dme::field-error name="exams"/>

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($catalogue as $category => $exams)
                    <div>
                        <h3 class="mb-1.5 text-xs font-semibold tracking-wide text-ink-500 uppercase">{{ $category }}</h3>
                        <div class="space-y-1">
                            @foreach ($exams as $exam)
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-ink-50">
                                    <input type="checkbox" name="exams[]" value="{{ $exam }}"
                                           @checked(in_array($exam, (array) old('exams', []), true))
                                           class="h-4 w-4 rounded border-ink-300 text-clinic-600">
                                    {{ $exam }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </fieldset>

        <div class="flex flex-wrap items-center gap-2">
            <button type="submit" class="k-btn-primary">Créer la demande</button>
            <a href="{{ route('dme.patients.show', $patient) }}" class="k-btn-ghost">Annuler</a>
        </div>
    </form>
@endsection
