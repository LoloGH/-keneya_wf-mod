@extends('dme::layouts.app')

@section('title', 'Hospitalisations')

@section('content')
    <x-dme::page-header title="Hospitalisations" subtitle="{{ $hospitalizations->total() }} séjour(s)."/>

    <form method="GET" class="k-card mb-4 flex flex-wrap gap-3 p-4">
        <div class="min-w-56 flex-1">
            <label for="q" class="sr-only">Rechercher</label>
            <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="k-input"
                   placeholder="N° de séjour ou patient...">
        </div>
        <div>
            <label for="status" class="sr-only">Statut</label>
            <select id="status" name="status" class="k-select">
                <option value="">Tous les statuts</option>
                @foreach (\Keneya\Dme\Models\Hospitalization::STATUSES as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="service" class="sr-only">Service</label>
            <select id="service" name="service" class="k-select">
                <option value="">Tous les services</option>
                @foreach ($services as $service)
                    <option value="{{ $service->id }}" @selected((string) ($filters['service'] ?? '') === (string) $service->id)>
                        {{ $service->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="k-btn-primary">Filtrer</button>
    </form>

    <div class="k-card">
        @if ($hospitalizations->isEmpty())
            <x-dme::empty-state icon="bed" title="Aucune hospitalisation"
                           message="Les admissions se créent depuis le dossier d'un patient."/>
        @else
            <div class="overflow-x-auto">
                <table class="k-table">
                    <caption class="sr-only">Séjours hospitaliers</caption>
                    <thead>
                        <tr>
                            <th scope="col">N°</th>
                            <th scope="col">Patient</th>
                            <th scope="col">Admission</th>
                            <th scope="col">Sortie</th>
                            <th scope="col">Service</th>
                            <th scope="col">Chambre</th>
                            <th scope="col">Statut</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($hospitalizations as $stay)
                            <tr>
                                <td class="font-mono text-xs">{{ $stay->hospitalization_number }}</td>
                                <td>
                                    <a href="{{ route('dme.patients.show', $stay->patient) }}"
                                       class="font-medium text-ink-900 hover:text-clinic-700 hover:underline">
                                        {{ $stay->patient->fullName() }}
                                    </a>
                                    <span class="block text-xs text-ink-500">{{ $stay->patient->ageLabel() }}</span>
                                </td>
                                <td class="whitespace-nowrap">{{ $stay->admitted_at->translatedFormat('d M Y') }}</td>
                                <td class="whitespace-nowrap">
                                    {{ $stay->discharged_at?->translatedFormat('d M Y') ?? '-' }}
                                </td>
                                <td>{{ $stay->service?->name ?? '-' }}</td>
                                <td>{{ $stay->room ? $stay->room.' · '.$stay->bed : '-' }}</td>
                                <td><x-dme::status-badge :status="$stay->status" :label="$stay->statusLabel()"/></td>
                                <td class="text-right">
                                    <a href="{{ route('dme.hospitalizations.show', $stay) }}" class="k-btn-ghost k-btn-sm">
                                        Suivi <x-dme::icon name="chevron-right" class="h-3.5 w-3.5"/>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $hospitalizations->links() }}</div>
        @endif
    </div>
@endsection
