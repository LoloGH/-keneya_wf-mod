@extends('dme::layouts.app')

@section('title', 'Rendez-vous '.$appointment->appointment_number)

@section('content')
    <x-dme::page-header :title="'Rendez-vous '.$appointment->appointment_number"
                   :subtitle="$appointment->scheduled_for->translatedFormat('l d F Y à H:i')"
                   :breadcrumbs="[
                       'Rendez-vous' => route('dme.appointments.index'),
                       $appointment->patient->fullName() => route('dme.patients.show', $appointment->patient),
                       $appointment->appointment_number => null,
                   ]"/>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <section class="k-card">
                <div class="k-card-header">
                    <h2 class="k-card-title">Détail du rendez-vous</h2>
                    <x-dme::status-badge :status="$appointment->status" :label="$appointment->statusLabel()"/>
                </div>
                <dl class="k-card-body grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        'Date et heure' => $appointment->scheduled_for->translatedFormat('l d F Y à H:i'),
                        'Durée' => $appointment->duration_minutes.' minutes',
                        'Médecin' => $appointment->doctor?->displayName() ?? 'Non attribué',
                        'Service' => $appointment->service?->name ?? 'Non précisé',
                        'Motif' => $appointment->reason ?: 'Consultation',
                        'Rappel SMS' => $appointment->reminder_sent_at
                            ? 'Programmé le '.$appointment->reminder_sent_at->translatedFormat('d M Y à H:i')
                            : ($appointment->reminder_enabled ? 'Activé' : 'Désactivé'),
                    ] as $label => $value)
                        <div>
                            <dt class="text-xs text-ink-500">{{ $label }}</dt>
                            <dd class="mt-0.5 text-sm font-medium text-ink-900">{{ $value }}</dd>
                        </div>
                    @endforeach
                    @if ($appointment->notes)
                        <div class="sm:col-span-2">
                            <dt class="text-xs text-ink-500">Notes</dt>
                            <dd class="mt-0.5 text-sm text-ink-800">{{ $appointment->notes }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            @can('update', $appointment)
                <section class="k-card">
                    <div class="k-card-header"><h2 class="k-card-title">Changer le statut</h2></div>
                    <form action="{{ route('dme.appointments.status', $appointment) }}" method="POST"
                          class="k-card-body flex flex-wrap items-end gap-3">
                        @csrf
                        @method('PATCH')
                        <div class="min-w-48">
                            <label for="status" class="k-label">Statut</label>
                            <select id="status" name="status" required class="k-select">
                                @foreach (\Keneya\Dme\Models\Appointment::STATUSES as $value => $label)
                                    <option value="{{ $value }}" @selected($appointment->status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="k-btn-primary">Mettre à jour</button>
                    </form>
                </section>
            @endcan
        </div>

        <div class="space-y-4">
            <section class="k-card">
                <div class="k-card-header"><h2 class="k-card-title">Patient</h2></div>
                <div class="k-card-body">
                    <x-dme::patient-header :patient="$appointment->patient" compact/>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('dme.patients.show', $appointment->patient) }}" class="k-btn-secondary k-btn-sm">
                            Ouvrir le dossier
                        </a>
                        @can('consultations.create')
                            <a href="{{ route('dme.consultations.create', $appointment->patient) }}" class="k-btn-primary k-btn-sm">
                                Démarrer la consultation
                            </a>
                        @endcan
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection
