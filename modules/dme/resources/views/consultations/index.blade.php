@extends('dme::layouts.app')

@section('title', 'Consultations')

@section('content')
    <x-dme::page-header title="Consultations" subtitle="{{ $consultations->total() }} consultation(s) enregistrée(s)."/>

    <form method="GET" class="k-card mb-4 flex flex-wrap gap-3 p-4">
        <div class="min-w-56 flex-1">
            <label for="q" class="sr-only">Rechercher</label>
            <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="k-input"
                   placeholder="N° de consultation ou patient...">
        </div>
        <div>
            <label for="status" class="sr-only">Statut</label>
            <select id="status" name="status" class="k-select">
                <option value="">Tous les statuts</option>
                @foreach (\Keneya\Dme\Models\Consultation::STATUSES as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="k-btn-primary">Filtrer</button>
    </form>

    <div class="k-card">
        @if ($consultations->isEmpty())
            <x-dme::empty-state icon="stethoscope" title="Aucune consultation"
                           message="Les consultations se créent depuis le dossier d'un patient."/>
        @else
            <div class="overflow-x-auto">
                <table class="k-table">
                    <caption class="sr-only">Liste des consultations</caption>
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">N°</th>
                            <th scope="col">Patient</th>
                            <th scope="col">Motif</th>
                            <th scope="col">Médecin</th>
                            <th scope="col">Service</th>
                            <th scope="col">Statut</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($consultations as $consultation)
                            <tr>
                                <td class="whitespace-nowrap">{{ $consultation->started_at->translatedFormat('d M Y H:i') }}</td>
                                <td class="font-mono text-xs">{{ $consultation->consultation_number }}</td>
                                <td>
                                    <a href="{{ route('dme.patients.show', $consultation->patient) }}"
                                       class="font-medium text-ink-900 hover:text-clinic-700 hover:underline">
                                        {{ $consultation->patient->fullName() }}
                                    </a>
                                    <span class="block font-mono text-[11px] text-ink-400">
                                        {{ $consultation->patient->patient_number }}
                                    </span>
                                </td>
                                <td class="max-w-xs truncate">{{ $consultation->reason ?: $consultation->typeLabel() }}</td>
                                <td>{{ $consultation->doctor?->displayName() ?? '-' }}</td>
                                <td>{{ $consultation->service?->name ?? '-' }}</td>
                                <td><x-dme::status-badge :status="$consultation->status" :label="$consultation->statusLabel()"/></td>
                                <td class="text-right">
                                    <a href="{{ route('dme.consultations.show', $consultation) }}" class="k-btn-ghost k-btn-sm">
                                        Ouvrir <x-dme::icon name="chevron-right" class="h-3.5 w-3.5"/>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $consultations->links() }}</div>
        @endif
    </div>
@endsection
