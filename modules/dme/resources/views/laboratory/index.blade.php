@extends('dme::layouts.app')

@section('title', 'Laboratoire')

@section('content')
    <x-dme::page-header title="Laboratoire" subtitle="{{ $orders->total() }} demande(s) d'analyse."/>

    <form method="GET" class="k-card mb-4 flex flex-wrap gap-3 p-4">
        <div class="min-w-56 flex-1">
            <label for="q" class="sr-only">Rechercher</label>
            <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="k-input"
                   placeholder="N° de demande ou patient...">
        </div>
        <div>
            <label for="status" class="sr-only">Statut</label>
            <select id="status" name="status" class="k-select">
                <option value="">Tous les statuts</option>
                @foreach (\Keneya\Dme\Models\LabOrder::STATUSES as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="priority" class="sr-only">Urgence</label>
            <select id="priority" name="priority" class="k-select">
                <option value="">Toutes urgences</option>
                @foreach (\Keneya\Dme\Models\LabOrder::PRIORITIES as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="k-btn-primary">Filtrer</button>
    </form>

    <div class="k-card">
        @if ($orders->isEmpty())
            <x-dme::empty-state icon="flask" title="Aucune demande d'analyse"
                           message="Les demandes se créent depuis le dossier d'un patient ou depuis une consultation."/>
        @else
            <div class="overflow-x-auto">
                <table class="k-table">
                    <caption class="sr-only">Demandes de laboratoire</caption>
                    <thead>
                        <tr>
                            <th scope="col">N°</th>
                            <th scope="col">Demandé le</th>
                            <th scope="col">Patient</th>
                            <th scope="col">Examens</th>
                            <th scope="col">Prescripteur</th>
                            <th scope="col">Urgence</th>
                            <th scope="col">Statut</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td class="font-mono text-xs">{{ $order->order_number }}</td>
                                <td class="whitespace-nowrap">{{ $order->requested_at->translatedFormat('d M Y H:i') }}</td>
                                <td>
                                    <a href="{{ route('dme.patients.show', $order->patient) }}"
                                       class="font-medium text-ink-900 hover:text-clinic-700 hover:underline">
                                        {{ $order->patient->fullName() }}
                                    </a>
                                </td>
                                <td class="max-w-xs truncate">{{ $order->items->pluck('exam_name')->implode(', ') }}</td>
                                <td>{{ $order->doctor?->displayName() ?? '-' }}</td>
                                <td><x-dme::status-badge :status="$order->priority" :label="$order->priorityLabel()"/></td>
                                <td><x-dme::status-badge :status="$order->status" :label="$order->statusLabel()"/></td>
                                <td class="text-right">
                                    <a href="{{ route('dme.laboratory.show', $order) }}" class="k-btn-ghost k-btn-sm">
                                        Ouvrir <x-dme::icon name="chevron-right" class="h-3.5 w-3.5"/>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $orders->links() }}</div>
        @endif
    </div>
@endsection
