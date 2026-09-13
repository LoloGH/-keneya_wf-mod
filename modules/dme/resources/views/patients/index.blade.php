@extends('dme::layouts.app')

@section('title', 'Patients')

@section('content')
    <x-dme::page-header title="Patients"
                   subtitle="{{ $patients->total() }} dossier(s), recherche par nom, numéro de dossier médical, téléphone ou date de naissance.">
        <x-slot:actions>
            @can('patients.view')
                <a href="{{ route('dme.patients.export', request()->query()) }}" class="k-btn-secondary">
                    <x-dme::icon name="download" class="h-4 w-4"/> Exporter
                </a>
            @endcan
            @can('patients.create')
                <a href="{{ route('dme.patients.create') }}" class="k-btn-primary">
                    <x-dme::icon name="plus" class="h-4 w-4"/> Nouveau patient
                </a>
            @endcan
        </x-slot:actions>
    </x-dme::page-header>

    {{-- Recherche et filtres (§11) --}}
    <form method="GET" action="{{ route('dme.patients.index') }}" class="k-card mb-4 p-4"
          x-data="{ showFilters: {{ collect($filters)->except('q')->filter()->isNotEmpty() ? 'true' : 'false' }} }">
        <div class="flex flex-wrap gap-3">
            <div class="min-w-56 flex-1">
                <label for="q" class="sr-only">Rechercher un patient</label>
                <div class="relative">
                    <x-dme::icon name="search" class="pointer-events-none absolute top-1/2 left-3 h-4.5 w-4.5 -translate-y-1/2 text-ink-400"/>
                    <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="k-input pl-10"
                           placeholder="Nom, prénom, n° dossier, téléphone, 12/03/1984...">
                </div>
            </div>
            <button type="submit" class="k-btn-primary">Rechercher</button>
            <button type="button" class="k-btn-secondary" @click="showFilters = !showFilters"
                    :aria-expanded="showFilters">Filtres</button>
            @if (collect($filters)->filter()->isNotEmpty())
                <a href="{{ route('dme.patients.index') }}" class="k-btn-ghost">Réinitialiser</a>
            @endif
        </div>

        <div x-show="showFilters" x-collapse x-cloak class="mt-4 grid gap-3 border-t border-ink-100 pt-4 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label for="sex" class="k-label">Sexe</label>
                <select id="sex" name="sex" class="k-select">
                    <option value="">Tous</option>
                    <option value="male" @selected(($filters['sex'] ?? '') === 'male')>Homme</option>
                    <option value="female" @selected(($filters['sex'] ?? '') === 'female')>Femme</option>
                </select>
            </div>
            <div>
                <label for="doctor" class="k-label">Médecin traitant</label>
                <select id="doctor" name="doctor" class="k-select">
                    <option value="">Tous</option>
                    @foreach ($doctors as $doctor)
                        <option value="{{ $doctor->id }}" @selected((string) ($filters['doctor'] ?? '') === (string) $doctor->id)>
                            {{ $doctor->displayName() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="k-label">Statut</label>
                <select id="status" name="status" class="k-select">
                    <option value="">Tous</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Actif</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactif</option>
                    <option value="deceased" @selected(($filters['status'] ?? '') === 'deceased')>Décédé</option>
                    <option value="archived" @selected(($filters['status'] ?? '') === 'archived')>Archivé</option>
                </select>
            </div>
            <div class="flex gap-2">
                <div class="flex-1">
                    <label for="age_min" class="k-label">Âge min.</label>
                    <input id="age_min" type="number" name="age_min" min="0" max="120"
                           value="{{ $filters['age_min'] ?? '' }}" class="k-input">
                </div>
                <div class="flex-1">
                    <label for="age_max" class="k-label">Âge max.</label>
                    <input id="age_max" type="number" name="age_max" min="0" max="120"
                           value="{{ $filters['age_max'] ?? '' }}" class="k-input">
                </div>
            </div>
            <div>
                <label for="period" class="k-label">Créé</label>
                <select id="period" name="period" class="k-select">
                    <option value="">Sans limite</option>
                    <option value="today" @selected(($filters['period'] ?? '') === 'today')>Aujourd'hui</option>
                    <option value="week" @selected(($filters['period'] ?? '') === 'week')>7 derniers jours</option>
                    <option value="month" @selected(($filters['period'] ?? '') === 'month')>30 derniers jours</option>
                </select>
            </div>
        </div>
    </form>

    @if ($patients->isEmpty())
        <div class="k-card">
            <x-dme::empty-state icon="users" title="Aucun patient ne correspond"
                           message="Ajustez la recherche ou les filtres, ou créez un nouveau dossier patient.">
                <x-slot:action>
                    @can('patients.create')
                        <a href="{{ route('dme.patients.create') }}" class="k-btn-primary">
                            <x-dme::icon name="plus" class="h-4 w-4"/> Nouveau patient
                        </a>
                    @endcan
                </x-slot:action>
            </x-dme::empty-state>
        </div>
    @else
        {{-- Tableau desktop --}}
        <div class="k-card hidden overflow-hidden md:block">
            <div class="overflow-x-auto">
                <table class="k-table">
                    <caption class="sr-only">Liste des patients</caption>
                    <thead>
                        <tr>
                            <th scope="col">Patient</th>
                            <th scope="col">N° dossier</th>
                            <th scope="col">Sexe</th>
                            <th scope="col">Âge</th>
                            <th scope="col">Téléphone</th>
                            <th scope="col">Dernière consultation</th>
                            <th scope="col">Statut</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($patients as $patient)
                            <tr>
                                <td>
                                    <a href="{{ route('dme.patients.show', $patient) }}"
                                       class="flex items-center gap-2.5 font-medium text-ink-900 hover:text-clinic-700">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-clinic-100 text-xs font-semibold text-clinic-700">
                                            {{ $patient->initials() }}
                                        </span>
                                        {{ $patient->fullName() }}
                                    </a>
                                </td>
                                <td class="font-mono text-xs">{{ $patient->patient_number }}</td>
                                <td>{{ $patient->sexLabel() }}</td>
                                <td class="tabular-nums">{{ $patient->ageLabel() }}</td>
                                <td class="tabular-nums">{{ $patient->phone ?: '-' }}</td>
                                <td>
                                    {{ $patient->last_consultation_at
                                        ? \Illuminate\Support\Carbon::parse($patient->last_consultation_at)->translatedFormat('d M Y')
                                        : '-' }}
                                </td>
                                <td><x-dme::status-badge :status="$patient->status" :label="ucfirst($patient->status)"/></td>
                                <td class="text-right">
                                    <a href="{{ route('dme.patients.show', $patient) }}" class="k-btn-ghost k-btn-sm">
                                        Dossier <x-dme::icon name="chevron-right" class="h-3.5 w-3.5"/>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Cartes mobile (§8) : le tableau devient une liste sous 768px --}}
        <div class="space-y-2.5 md:hidden">
            @foreach ($patients as $patient)
                <a href="{{ route('dme.patients.show', $patient) }}" class="k-card block p-4">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-clinic-100 text-sm font-semibold text-clinic-700">
                            {{ $patient->initials() }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-ink-900">{{ $patient->fullName() }}</p>
                            <p class="font-mono text-xs text-ink-500">{{ $patient->patient_number }}</p>
                            <p class="mt-1 text-sm text-ink-600">
                                {{ $patient->sexLabel() }} · {{ $patient->ageLabel() }}
                                @if ($patient->phone) · {{ $patient->phone }} @endif
                            </p>
                        </div>
                        <x-dme::status-badge :status="$patient->status" :label="ucfirst($patient->status)"/>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-4">{{ $patients->links() }}</div>
    @endif
@endsection
