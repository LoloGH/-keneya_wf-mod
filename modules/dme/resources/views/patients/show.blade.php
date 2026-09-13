@extends('dme::layouts.app')

@section('title', $patient->fullName())

@php
    /** Onglets du dossier médical électronique (§14). */
    $tabs = [
        'resume' => 'Résumé',
        'consultations' => 'Consultations',
        'antecedents' => 'Antécédents',
        'allergies' => 'Allergies',
        'medicaments' => 'Médicaments',
        'ordonnances' => 'Ordonnances',
        'laboratoire' => 'Laboratoire',
        'imagerie' => 'Imagerie',
        'hospitalisations' => 'Hospitalisations',
        'soins' => 'Soins',
        'rendez-vous' => 'Rendez-vous',
        'documents' => 'Documents',
        'historique' => 'Historique',
        'audit' => 'Audit',
    ];
@endphp

@section('content')
    <nav class="mb-3 flex items-center gap-1 text-xs text-ink-500" aria-label="Fil d'Ariane">
        <a href="{{ route('dme.patients.index') }}" class="flex items-center gap-1 hover:text-clinic-700 hover:underline">
            <x-dme::icon name="arrow-left" class="h-3.5 w-3.5"/> Patients
        </a>
        <x-dme::icon name="chevron-right" class="h-3.5 w-3.5"/>
        <span class="font-medium text-ink-700" aria-current="page">{{ $patient->fullName() }}</span>
    </nav>

    {{-- En-tête patient permanent (§13) --}}
    <section class="k-card mb-4">
        <div class="p-4 sm:p-5">
            <x-dme::patient-header :patient="$patient">
                <x-slot:actions>
                    @can('update', $patient)
                        <a href="{{ route('dme.patients.edit', $patient) }}" class="k-btn-secondary k-btn-sm">Modifier</a>
                    @endcan
                    <a href="{{ route('dme.patients.summary-pdf', $patient) }}" target="_blank" rel="noopener"
                       class="k-btn-secondary k-btn-sm">
                        <x-dme::icon name="print" class="h-4 w-4"/> Fiche PDF
                    </a>

                    {{-- Archiver ne détruit rien et se défait : un bouton
                         suffit. Détruire est plus bas, et demande davantage. --}}
                    @can('archive', $patient)
                        <form method="POST" action="{{ route('dme.patients.archive', $patient) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="k-btn-secondary k-btn-sm">Archiver le dossier</button>
                        </form>
                    @endcan

                    @can('restore', $patient)
                        <form method="POST" action="{{ route('dme.patients.restore', $patient) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="k-btn-secondary k-btn-sm">Restaurer le dossier</button>
                        </form>
                    @endcan
                </x-slot:actions>
            </x-dme::patient-header>

            {{-- Alertes cliniques permanentes (§13) --}}
            @if ($patient->criticalAllergies()->isNotEmpty() || $patient->activeConditions()->isNotEmpty())
                <div class="mt-4">
                    <x-dme::medical-alerts :patient="$patient"/>
                </div>
            @endif

            @if ($patient->status === 'archived')
                <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
                    Ce dossier est <strong>archivé</strong> : il ne figure plus dans la liste des
                    patients et n'est plus modifiable. Il reste consultable dans son intégralité,
                    et se restaure.
                </div>
            @endif

            {{-- Suppression définitive (§40). Réservée à l'administrateur, et
                 seulement sur un dossier déjà archivé : le passage par
                 l'archive laisse le temps de se raviser, et rend le geste
                 délibéré. Le numéro de dossier médical doit être retapé, comme dans
                 l'application hôte : cocher une case ne suffit pas à détruire
                 un dossier médical. --}}
            @can('purge', $patient)
                <details class="mt-4 rounded-lg border border-red-300 bg-red-50 p-3">
                    <summary class="cursor-pointer text-sm font-medium text-red-800">
                        Supprimer définitivement ce dossier
                    </summary>

                    <p class="mt-2 text-sm text-red-800">
                        Le dossier et tout son contenu clinique, consultations, ordonnances,
                        examens, hospitalisations, documents, seront détruits. Cette action est
                        irréversible. Seule la trace au journal d'audit subsistera.
                    </p>

                    <form method="POST" action="{{ route('dme.patients.destroy', $patient) }}"
                          class="mt-3 space-y-3">
                        @csrf
                        @method('DELETE')

                        <div>
                            <label for="purge-number" class="k-label">
                                Retapez le numéro de dossier médical ({{ $patient->patient_number }})
                            </label>
                            <input id="purge-number" name="patient_number" type="text" required
                                   autocomplete="off" class="k-input">
                            @error('patient_number')
                                <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="purge-reason" class="k-label">Motif</label>
                            <input id="purge-reason" name="reason" type="text" required maxlength="500"
                                   class="k-input" placeholder="Pourquoi ce dossier est-il détruit ?">
                            @error('reason')
                                <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <button type="submit" class="k-btn-danger k-btn-sm">
                                Supprimer définitivement
                            </button>
                        </div>
                    </form>
                </details>
            @endcan

            {{-- Actions rapides du DME (§51) --}}
            <div class="mt-4 flex flex-wrap gap-2 border-t border-ink-100 pt-4">
                @can('consultations.create')
                    <a href="{{ route('dme.consultations.create', $patient) }}" class="k-btn-primary k-btn-sm">
                        <x-dme::icon name="plus" class="h-3.5 w-3.5"/> Consultation
                    </a>
                @endcan
                @can('prescriptions.create')
                    <a href="{{ route('dme.prescriptions.create', $patient) }}" class="k-btn-secondary k-btn-sm">
                        <x-dme::icon name="plus" class="h-3.5 w-3.5"/> Ordonnance
                    </a>
                @endcan
                @can('laboratory.orders.create')
                    <a href="{{ route('dme.laboratory.create', $patient) }}" class="k-btn-secondary k-btn-sm">
                        <x-dme::icon name="plus" class="h-3.5 w-3.5"/> Examen biologique
                    </a>
                @endcan
                @can('imaging.create')
                    <a href="{{ route('dme.imaging.create', $patient) }}" class="k-btn-secondary k-btn-sm">
                        <x-dme::icon name="plus" class="h-3.5 w-3.5"/> Imagerie
                    </a>
                @endcan
                @can('hospitalizations.create')
                    <a href="{{ route('dme.hospitalizations.create', $patient) }}" class="k-btn-secondary k-btn-sm">
                        <x-dme::icon name="plus" class="h-3.5 w-3.5"/> Admission
                    </a>
                @endcan
                @can('appointments.manage')
                    <a href="{{ route('dme.patients.show', [$patient, 'tab' => 'rendez-vous']) }}" class="k-btn-secondary k-btn-sm">
                        <x-dme::icon name="plus" class="h-3.5 w-3.5"/> Rendez-vous
                    </a>
                @endcan
            </div>
        </div>

        {{-- Onglets défilables sur mobile (§8) --}}
        <div class="px-4 sm:px-5">
            <nav class="k-tabs" aria-label="Sections du dossier médical">
                @foreach ($tabs as $key => $label)
                    <a href="{{ route('dme.patients.show', [$patient, 'tab' => $key]) }}"
                       class="{{ $tab === $key ? 'k-tab-active' : 'k-tab' }}"
                       @if ($tab === $key) aria-current="page" @endif>
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
        </div>
    </section>

    @include('dme::patients.tabs.'.(view()->exists('dme::patients.tabs.'.$tab) ? $tab : 'resume'))
@endsection
