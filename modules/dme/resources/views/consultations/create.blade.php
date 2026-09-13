@extends('dme::layouts.app')

@section('title', 'Nouvelle consultation')

@section('content')
    <x-dme::page-header title="Nouvelle consultation"
                   :subtitle="$patient->fullName().' - '.$patient->patient_number"
                   :breadcrumbs="[
                       'Patients' => route('dme.patients.index'),
                       $patient->fullName() => route('dme.patients.show', $patient),
                       'Nouvelle consultation' => null,
                   ]"/>

    <form action="{{ route('dme.consultations.store', $patient) }}" method="POST" novalidate>
        @csrf
        @include('dme::consultations._form', ['consultation' => null])

        <div class="mt-5 flex flex-wrap items-center gap-2">
            <button type="submit" name="action" value="save" class="k-btn-primary">Enregistrer</button>
            @can('prescriptions.create')
                <button type="submit" name="action" value="prescribe" class="k-btn-secondary">
                    Enregistrer et prescrire
                </button>
            @endcan
            <a href="{{ route('dme.patients.show', $patient) }}" class="k-btn-ghost">Annuler</a>
        </div>
    </form>
@endsection
