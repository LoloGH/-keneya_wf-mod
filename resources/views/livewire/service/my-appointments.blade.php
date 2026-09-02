<x-card title="Mes rendez-vous ({{ $appointments->count() }})" icon="planning">
    <p class="hint">Vos prochains rendez-vous.</p>

    <label class="field field--inline">
        <input type="checkbox" wire:model.live="pastToo">
        <span>Inclure les rendez-vous passes</span>
    </label>

    @if ($appointments->isEmpty())
        <p class="empty">Aucun rendez-vous a venir.</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Date</th><th>Patient</th><th>Service</th><th>Statut</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($appointments as $rdv)
                        <tr>
                            <td class="mono">{{ $rdv->scheduled_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <button type="button" class="referrals__patient"
                                        wire:click="showRecord({{ $rdv->patient_id }})">
                                    <strong>{{ $rdv->patient->name }}</strong>
                                    <span class="mono">{{ $rdv->patient->patient_code }}</span>
                                </button>
                            </td>
                            <td>{{ $rdv->service->name }}</td>
                            <td><span class="badge badge--{{ $rdv->status }}">{{ $rdv->statusLabel() }}</span></td>
                            <td>
                                @if ($rdv->status === \App\Models\Appointment::STATUS_SCHEDULED)
                                    <button type="button" class="btn btn--ghost"
                                            wire:click="cancel({{ $rdv->id }})"
                                            wire:confirm="Annuler ce rendez-vous ?">
                                        Annuler
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-card>
