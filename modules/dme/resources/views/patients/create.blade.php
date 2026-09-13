@extends('dme::layouts.app')

@section('title', 'Nouveau patient')

@section('content')
    <x-dme::page-header title="Nouveau patient"
                   subtitle="Créez un dossier médical électronique."
                   :breadcrumbs="['Patients' => route('dme.patients.index'), 'Nouveau patient' => null]"/>

    <form action="{{ route('dme.patients.store') }}" method="POST" novalidate>
        @csrf
        @include('dme::patients._form', ['patient' => null])

        <div class="mt-5 flex flex-wrap items-center gap-2">
            <button type="submit" name="action" value="save" class="k-btn-primary">Enregistrer</button>
            <button type="submit" name="action" value="open" class="k-btn-secondary">
                Enregistrer et ouvrir le dossier
            </button>
            <a href="{{ route('dme.patients.index') }}" class="k-btn-ghost">Annuler</a>
        </div>
    </form>
@endsection
