@extends('dme::layouts.app')

@section('title', 'Rendez-vous')

@section('content')
    <x-dme::page-header title="Rendez-vous"
                   :subtitle="$appointments->count().' rendez-vous du '.$start->translatedFormat('d M').' au '.$end->translatedFormat('d M Y')"/>

    {{-- Sélecteur de vue jour / semaine / mois (§27) --}}
    <div class="k-card mb-4 p-4">
        <div class="flex flex-wrap items-center gap-3">
            <div class="inline-flex rounded-lg border border-ink-300 p-0.5" role="group" aria-label="Mode d'affichage">
                @foreach (['day' => 'Jour', 'week' => 'Semaine', 'month' => 'Mois'] as $value => $label)
                    <a href="{{ route('dme.appointments.index', array_merge(request()->query(), ['view' => $value])) }}"
                       class="rounded-md px-3 py-1.5 text-sm font-medium transition
                              {{ $view === $value ? 'bg-clinic-600 text-white' : 'text-ink-600 hover:bg-ink-100' }}"
                       @if ($view === $value) aria-current="true" @endif>
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            @php
                $step = ['day' => '1 day', 'week' => '1 week', 'month' => '1 month'][$view] ?? '1 week';
                $prev = $anchor->copy()->sub($step)->toDateString();
                $next = $anchor->copy()->add($step)->toDateString();
            @endphp

            <div class="inline-flex items-center gap-1">
                <a href="{{ route('dme.appointments.index', array_merge(request()->query(), ['date' => $prev])) }}"
                   class="k-btn-secondary k-btn-sm" aria-label="Période précédente">
                    <x-dme::icon name="chevron-left" class="h-4 w-4"/>
                </a>
                <a href="{{ route('dme.appointments.index', array_merge(request()->query(), ['date' => now()->toDateString()])) }}"
                   class="k-btn-secondary k-btn-sm">Aujourd'hui</a>
                <a href="{{ route('dme.appointments.index', array_merge(request()->query(), ['date' => $next])) }}"
                   class="k-btn-secondary k-btn-sm" aria-label="Période suivante">
                    <x-dme::icon name="chevron-right" class="h-4 w-4"/>
                </a>
            </div>

            <form method="GET" class="ml-auto flex flex-wrap gap-2">
                <input type="hidden" name="view" value="{{ $view }}">
                <input type="hidden" name="date" value="{{ $anchor->toDateString() }}">
                <div>
                    <label for="doctor" class="sr-only">Médecin</label>
                    <select id="doctor" name="doctor" class="k-select">
                        <option value="">Tous les médecins</option>
                        @foreach ($doctors as $doctor)
                            <option value="{{ $doctor->id }}" @selected((string) ($filters['doctor'] ?? '') === (string) $doctor->id)>
                                {{ $doctor->displayName() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="sr-only">Statut</label>
                    <select id="status" name="status" class="k-select">
                        <option value="">Tous les statuts</option>
                        @foreach (\Keneya\Dme\Models\Appointment::STATUSES as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="k-btn-primary">Filtrer</button>
            </form>
        </div>
    </div>

    @if ($appointments->isEmpty())
        <div class="k-card">
            <x-dme::empty-state icon="calendar" title="Aucun rendez-vous sur la période"
                           message="Les rendez-vous se programment depuis le dossier d'un patient."/>
        </div>
    @else
        <div class="space-y-4">
            @php $cursor = $start->copy(); @endphp
            @while ($cursor <= $end)
                @php $key = $cursor->format('Y-m-d'); $dayAppointments = $grouped[$key] ?? collect(); @endphp
                @if ($dayAppointments->isNotEmpty() || $view === 'day')
                    <section class="k-card">
                        <div class="k-card-header">
                            <h2 class="k-card-title {{ $cursor->isToday() ? 'text-clinic-700' : '' }}">
                                {{ $cursor->translatedFormat('l d F Y') }}
                                @if ($cursor->isToday())
                                    <span class="k-badge-info ml-1">Aujourd'hui</span>
                                @endif
                            </h2>
                            <span class="text-xs text-ink-500">{{ $dayAppointments->count() }} rendez-vous</span>
                        </div>

                        @if ($dayAppointments->isEmpty())
                            <p class="px-4 py-6 text-center text-sm text-ink-500">Aucun rendez-vous ce jour.</p>
                        @else
                            <ul class="divide-y divide-ink-100">
                                @foreach ($dayAppointments as $appointment)
                                    <li>
                                        <a href="{{ route('dme.appointments.show', $appointment) }}"
                                           class="flex flex-wrap items-center gap-3 px-4 py-3 hover:bg-clinic-50/40">
                                            <span class="w-24 shrink-0 font-mono text-sm text-ink-700">
                                                {{ $appointment->scheduled_for->format('H:i') }}
                                                <span class="text-ink-400">- {{ $appointment->endsAt()->format('H:i') }}</span>
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block font-medium text-ink-900">
                                                    {{ $appointment->patient->fullName() }}
                                                </span>
                                                <span class="block text-xs text-ink-500">
                                                    {{ $appointment->reason ?: 'Consultation' }}
                                                    @if ($appointment->doctor) · {{ $appointment->doctor->displayName() }} @endif
                                                    @if ($appointment->service) · {{ $appointment->service->name }} @endif
                                                </span>
                                            </span>
                                            <x-dme::status-badge :status="$appointment->status" :label="$appointment->statusLabel()"/>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endif
                @php $cursor->addDay(); @endphp
            @endwhile
        </div>
    @endif
@endsection
