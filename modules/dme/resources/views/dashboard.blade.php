@extends('dme::layouts.app')

@section('title', 'Tableau de bord')

@section('content')
    <x-dme::page-header
        title="Bonjour, {{ auth()->user()->displayName() }}"
        subtitle="Activité médicale du {{ now()->translatedFormat('l d F Y') }}">
        <x-slot:actions>
            @can('patients.create')
                <a href="{{ route('dme.patients.create') }}" class="k-btn-primary">
                    <x-dme::icon name="plus" class="h-4 w-4"/> Nouveau patient
                </a>
            @endcan
        </x-slot:actions>
    </x-dme::page-header>

    {{-- Indicateurs du jour (§10) --}}
    <section aria-label="Indicateurs du jour"
             class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-7">
        @foreach ($kpis as $key => $kpi)
            <a href="{{ $kpi['route'] }}"
               class="k-card p-4 transition hover:border-clinic-300 hover:shadow-md">
                <p class="text-xs font-medium text-ink-500">{{ $kpi['label'] }}</p>
                <p class="mt-1.5 text-2xl font-semibold tabular-nums text-ink-900">{{ $kpi['value'] }}</p>
                <p class="mt-0.5 text-[11px] text-ink-400">{{ $kpi['hint'] }}</p>
            </a>
        @endforeach
    </section>

    <div class="mt-5 grid gap-5 xl:grid-cols-3">

        {{-- Graphique d'activité (§10) --}}
        <section class="k-card xl:col-span-2">
            <div class="k-card-header">
                <div>
                    <h2 class="k-card-title">Activité médicale</h2>
                    <p class="text-xs text-ink-500">Consultations des 14 derniers jours</p>
                </div>
                <span class="k-badge-info">{{ $activityChart->sum('value') }} au total</span>
            </div>
            <div class="k-card-body">
                @php $peak = max(1, $activityChart->max('value')); @endphp
                <div class="flex h-44 items-end gap-1.5" role="img"
                     aria-label="Histogramme des consultations sur 14 jours, maximum {{ $peak }}">
                    @foreach ($activityChart as $day)
                        <div class="group flex h-full flex-1 flex-col items-center justify-end gap-1.5">
                            <span class="text-[10px] font-medium text-ink-500 opacity-0 group-hover:opacity-100">
                                {{ $day['value'] }}
                            </span>
                            <div class="w-full shrink-0 rounded-t bg-clinic-500 transition group-hover:bg-clinic-600"
                                 style="height: {{ max(2, round($day['value'] / $peak * 82)) }}%"></div>
                            <span class="text-[10px] whitespace-nowrap text-ink-400">{{ $day['label'] }}</span>
                        </div>
                    @endforeach
                </div>

                {{-- Équivalent textuel du graphique (§48) --}}
                <details class="mt-3">
                    <summary class="cursor-pointer text-xs text-ink-500 hover:text-ink-800">
                        Voir les valeurs sous forme de tableau
                    </summary>
                    <table class="k-table mt-2">
                        <thead><tr><th>Jour</th><th>Consultations</th></tr></thead>
                        <tbody>
                            @foreach ($activityChart as $day)
                                <tr><td>{{ $day['label'] }}</td><td class="tabular-nums">{{ $day['value'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </details>
            </div>
        </section>

        {{-- Répartition par service (§10) --}}
        <section class="k-card">
            <div class="k-card-header">
                <h2 class="k-card-title">Répartition des consultations</h2>
                <span class="text-xs text-ink-400">30 jours</span>
            </div>
            <div class="k-card-body">
                @forelse ($serviceBreakdown as $row)
                    <div class="mb-3 last:mb-0">
                        <div class="flex items-baseline justify-between text-sm">
                            <span class="truncate font-medium text-ink-700">{{ $row['label'] }}</span>
                            <span class="ml-2 shrink-0 tabular-nums text-ink-500">
                                {{ $row['value'] }} · {{ $row['share'] }} %
                            </span>
                        </div>
                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-ink-100">
                            <div class="h-full rounded-full bg-clinic-500" style="width: {{ $row['share'] }}%"></div>
                        </div>
                    </div>
                @empty
                    <x-dme::empty-state icon="stethoscope" title="Aucune consultation sur la période"
                                   message="Les consultations enregistrées apparaîtront ici, réparties par service."/>
                @endforelse
            </div>
        </section>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-2">

        {{-- Activité récente (§10) --}}
        <section class="k-card">
            <div class="k-card-header">
                <h2 class="k-card-title">Activité récente</h2>
                @can('audit.view')
                    <a href="{{ route('dme.audit.index') }}" class="text-xs font-medium text-clinic-700 hover:underline">
                        Journal complet
                    </a>
                @endcan
            </div>
            <div class="k-card-body">
                @forelse ($recentActivity as $log)
                    <div class="flex gap-3 border-b border-ink-100 py-2.5 last:border-0 last:pb-0 first:pt-0">
                        <span class="w-11 shrink-0 pt-0.5 font-mono text-xs text-ink-400">
                            {{ $log->created_at->format('H:i') }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-ink-800">
                                <span class="font-medium">{{ $log->causer?->displayName() ?? 'Système' }}</span>
                                {{ mb_strtolower($log->actionLabel()) }}
                            </p>
                            <p class="truncate text-xs text-ink-500">{{ $log->description }}</p>
                        </div>
                    </div>
                @empty
                    <x-dme::empty-state icon="clipboard" title="Aucune activité enregistrée"
                                   message="Les actions réalisées dans l'application apparaîtront ici."/>
                @endforelse
            </div>
        </section>

        {{-- Prochains rendez-vous --}}
        <section class="k-card">
            <div class="k-card-header">
                <h2 class="k-card-title">Prochains rendez-vous</h2>
                @can('appointments.view')
                    <a href="{{ route('dme.appointments.index') }}" class="text-xs font-medium text-clinic-700 hover:underline">
                        Calendrier
                    </a>
                @endcan
            </div>
            <div class="k-card-body">
                @forelse ($upcomingAppointments as $appointment)
                    <a href="{{ route('dme.appointments.show', $appointment) }}"
                       class="flex items-center gap-3 border-b border-ink-100 py-2.5 last:border-0 last:pb-0 first:pt-0
                              hover:bg-clinic-50/40">
                        <div class="w-14 shrink-0 text-center">
                            <p class="text-xs font-semibold text-clinic-700">
                                {{ $appointment->scheduled_for->translatedFormat('d M') }}
                            </p>
                            <p class="font-mono text-xs text-ink-500">{{ $appointment->scheduled_for->format('H:i') }}</p>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-ink-900">{{ $appointment->patient->fullName() }}</p>
                            <p class="truncate text-xs text-ink-500">
                                {{ $appointment->reason ?: 'Consultation' }}
                                @if ($appointment->doctor) · {{ $appointment->doctor->displayName() }} @endif
                            </p>
                        </div>
                        <x-dme::status-badge :status="$appointment->status" :label="$appointment->statusLabel()"/>
                    </a>
                @empty
                    <x-dme::empty-state icon="calendar" title="Aucun rendez-vous à venir"
                                   message="Les rendez-vous programmés s'afficheront dans cette liste."/>
                @endforelse
            </div>
        </section>
    </div>
@endsection
