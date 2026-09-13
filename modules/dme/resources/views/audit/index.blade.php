@extends('dme::layouts.app')

@section('title', 'Journal d\'audit')

@section('content')
    <x-dme::page-header title="Journal d'audit"
                   subtitle="Registre en écriture seule : aucune entrée ne peut être modifiée ni supprimée, y compris par un administrateur."/>

    <form method="GET" class="k-card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6">
        <div>
            <label for="user" class="k-label">Utilisateur</label>
            <select id="user" name="user" class="k-select">
                <option value="">Tous</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected((string) ($filters['user'] ?? '') === (string) $user->id)>
                        {{ $user->displayName() }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="action" class="k-label">Action</label>
            <select id="action" name="action" class="k-select">
                <option value="">Toutes</option>
                @foreach ($actions as $action)
                    <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="outcome" class="k-label">Résultat</label>
            <select id="outcome" name="outcome" class="k-select">
                <option value="">Tous</option>
                <option value="allowed" @selected(($filters['outcome'] ?? '') === 'allowed')>Autorisé</option>
                <option value="denied" @selected(($filters['outcome'] ?? '') === 'denied')>Refusé</option>
                <option value="failed" @selected(($filters['outcome'] ?? '') === 'failed')>Échec</option>
            </select>
        </div>
        <div>
            <label for="from" class="k-label">Du</label>
            <input id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}" class="k-input">
        </div>
        <div>
            <label for="to" class="k-label">Au</label>
            <input id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}" class="k-input">
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="k-btn-primary">Filtrer</button>
            @if (collect($filters)->filter()->isNotEmpty())
                <a href="{{ route('dme.audit.index') }}" class="k-btn-ghost">Réinitialiser</a>
            @endif
        </div>
    </form>

    <div class="k-card">
        @if ($logs->isEmpty())
            <x-dme::empty-state icon="clipboard" title="Aucune entrée d'audit"
                           message="Ajustez les filtres, ou attendez que des actions soient réalisées dans l'application."/>
        @else
            <div class="overflow-x-auto">
                <table class="k-table">
                    <caption class="sr-only">Journal d'audit de l'application</caption>
                    <thead>
                        <tr>
                            <th scope="col">Date et heure</th>
                            <th scope="col">Utilisateur</th>
                            <th scope="col">Rôle</th>
                            <th scope="col">Action</th>
                            <th scope="col">Patient</th>
                            <th scope="col">Détail</th>
                            <th scope="col">Adresse IP</th>
                            <th scope="col">Résultat</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr class="{{ $log->outcome === 'denied' ? 'bg-red-50/50' : '' }}">
                                <td class="whitespace-nowrap font-mono text-xs">
                                    {{ $log->created_at->format('d/m/Y H:i:s') }}
                                </td>
                                <td class="font-medium text-ink-900">{{ $log->causer?->displayName() ?? 'Système' }}</td>
                                <td class="text-xs">
                                    {{ \Keneya\Dme\Support\Rbac::allRoleLabels()[$log->causer_role] ?? ($log->causer_role ?: '-') }}
                                </td>
                                <td>{{ $log->actionLabel() }}</td>
                                <td class="font-mono text-xs">
                                    @if ($log->patient)
                                        <a href="{{ route('dme.patients.show', $log->patient) }}" class="text-clinic-700 hover:underline">
                                            {{ $log->patient->patient_number }}
                                        </a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="max-w-sm truncate text-xs text-ink-500">{{ $log->description }}</td>
                                <td class="font-mono text-xs">{{ $log->ip_address ?: '-' }}</td>
                                <td>
                                    <x-dme::status-badge :status="$log->outcome"
                                        :label="$log->outcome === 'allowed' ? 'Autorisé' : ($log->outcome === 'denied' ? 'Refusé' : 'Échec')"/>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $logs->links() }}</div>
        @endif
    </div>
@endsection
