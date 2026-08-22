<section class="card" wire:poll.{{ config('keneya.poll_interval') }}>
    <h2 class="card__title">Rendez-vous du jour ({{ $appointments->count() }})</h2>

    @if ($appointments->isEmpty())
        <p class="empty">Aucun rendez-vous prevu aujourd'hui.</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Heure</th><th>Patient</th><th>Service</th><th>Medecin</th><th>Statut</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($appointments as $appointment)
                        <tr>
                            <td class="mono">{{ $appointment->scheduled_at->format('H:i') }}</td>
                            <td>
                                {{ $appointment->patient->name }}
                                <span class="mono">{{ $appointment->patient->patient_code }}</span>
                            </td>
                            <td>{{ $appointment->service->name }}</td>
                            <td>{{ $appointment->doctor->name() }}</td>
                            <td>
                                <span class="badge badge--{{ $appointment->status }}">
                                    {{ $appointment->statusLabel() }}
                                </span>
                            </td>
                            <td>
                                @if ($appointment->status === \App\Models\Appointment::STATUS_SCHEDULED)
                                    <div class="btn-row">
                                        <button type="button" class="btn btn--primary"
                                                wire:click="checkIn({{ $appointment->id }})">
                                            Orienter le patient
                                        </button>
                                        <button type="button" class="btn btn--ghost"
                                                wire:click="markNoShow({{ $appointment->id }})">
                                            Non presente
                                        </button>
                                        <button type="button" class="btn btn--ghost"
                                                wire:click="cancel({{ $appointment->id }})">
                                            Annuler
                                        </button>
                                    </div>
                                @elseif ($appointment->visit)
                                    <span class="hint">Ticket n° {{ $appointment->visit->token }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
