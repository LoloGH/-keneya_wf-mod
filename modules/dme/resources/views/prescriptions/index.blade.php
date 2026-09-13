@extends('dme::layouts.app')

@section('title', 'Ordonnances')

@section('content')
    <x-dme::page-header title="Ordonnances" subtitle="{{ $prescriptions->total() }} ordonnance(s)."/>

    <form method="GET" class="k-card mb-4 flex flex-wrap gap-3 p-4">
        <div class="min-w-56 flex-1">
            <label for="q" class="sr-only">Rechercher</label>
            <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="k-input"
                   placeholder="N° d'ordonnance ou patient...">
        </div>
        <div>
            <label for="status" class="sr-only">Statut</label>
            <select id="status" name="status" class="k-select">
                <option value="">Tous les statuts</option>
                @foreach (\Keneya\Dme\Models\Prescription::STATUSES as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="k-btn-primary">Filtrer</button>
    </form>

    <div class="k-card">
        @if ($prescriptions->isEmpty())
            <x-dme::empty-state icon="pill" title="Aucune ordonnance"
                           message="Les ordonnances se créent depuis le dossier d'un patient ou depuis une consultation."/>
        @else
            <div class="overflow-x-auto">
                <table class="k-table">
                    <caption class="sr-only">Liste des ordonnances</caption>
                    <thead>
                        <tr>
                            <th scope="col">N°</th>
                            <th scope="col">Date</th>
                            <th scope="col">Patient</th>
                            <th scope="col">Médicaments</th>
                            <th scope="col">Prescripteur</th>
                            <th scope="col">Statut</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($prescriptions as $prescription)
                            <tr>
                                <td class="font-mono text-xs">
                                    {{ $prescription->prescription_number }}
                                    @if ($prescription->hasAllergyWarnings())
                                        <span class="k-badge-danger ml-1">
                                            <x-dme::icon name="alert" class="h-3 w-3"/> Allergie
                                        </span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap">{{ $prescription->issued_on->translatedFormat('d M Y') }}</td>
                                <td>
                                    <a href="{{ route('dme.patients.show', $prescription->patient) }}"
                                       class="font-medium text-ink-900 hover:text-clinic-700 hover:underline">
                                        {{ $prescription->patient->fullName() }}
                                    </a>
                                </td>
                                <td class="max-w-xs truncate">
                                    {{ $prescription->items->pluck('medication_name')->implode(', ') ?: '-' }}
                                </td>
                                <td>{{ $prescription->doctor?->displayName() ?? '-' }}</td>
                                <td><x-dme::status-badge :status="$prescription->status" :label="$prescription->statusLabel()"/></td>
                                <td class="text-right">
                                    <a href="{{ route('dme.prescriptions.show', $prescription) }}" class="k-btn-ghost k-btn-sm">
                                        Ouvrir <x-dme::icon name="chevron-right" class="h-3.5 w-3.5"/>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $prescriptions->links() }}</div>
        @endif
    </div>
@endsection
