{{-- Rendez-vous du patient (§27) --}}
<div class="grid gap-4 lg:grid-cols-3">
    <section class="k-card lg:col-span-2">
        <div class="k-card-header">
            <h2 class="k-card-title">Rendez-vous</h2>
            <span class="text-xs text-ink-500">{{ $tabData['appointments']->total() }} au total</span>
        </div>

        @if ($tabData['appointments']->isEmpty())
            <x-dme::empty-state icon="calendar" title="Aucun rendez-vous"
                           message="Programmez un rendez-vous : le patient recevra une confirmation par SMS et un rappel la veille."/>
        @else
            <div class="overflow-x-auto">
                <table class="k-table">
                    <caption class="sr-only">Rendez-vous du patient</caption>
                    <thead>
                        <tr>
                            <th scope="col">Date et heure</th>
                            <th scope="col">N°</th>
                            <th scope="col">Motif</th>
                            <th scope="col">Médecin</th>
                            <th scope="col">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tabData['appointments'] as $appointment)
                            <tr>
                                <td class="whitespace-nowrap">
                                    <a href="{{ route('dme.appointments.show', $appointment) }}" class="hover:text-clinic-700 hover:underline">
                                        {{ $appointment->scheduled_for->translatedFormat('d M Y · H:i') }}
                                    </a>
                                </td>
                                <td class="font-mono text-xs">{{ $appointment->appointment_number }}</td>
                                <td>{{ $appointment->reason ?: 'Consultation' }}</td>
                                <td>{{ $appointment->doctor?->displayName() ?? '-' }}</td>
                                <td><x-dme::status-badge :status="$appointment->status" :label="$appointment->statusLabel()"/></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $tabData['appointments']->links() }}</div>
        @endif
    </section>

    @can('appointments.manage')
        <section class="k-card self-start">
            <div class="k-card-header"><h2 class="k-card-title">Programmer un rendez-vous</h2></div>
            <form action="{{ route('dme.appointments.store', $patient) }}" method="POST" class="k-card-body space-y-3">
                @csrf
                <div>
                    <label for="scheduled_for" class="k-label">Date et heure <span class="text-red-600" aria-hidden="true">*</span></label>
                    <input id="scheduled_for" name="scheduled_for" type="datetime-local" required
                           min="{{ now()->format('Y-m-d\TH:i') }}"
                           value="{{ now()->addDay()->setTime(9, 0)->format('Y-m-d\TH:i') }}" class="k-input">
                    <x-dme::field-error name="scheduled_for"/>
                </div>
                <div>
                    <label for="duration_minutes" class="k-label">Durée (minutes) <span class="text-red-600" aria-hidden="true">*</span></label>
                    <input id="duration_minutes" name="duration_minutes" type="number" required min="5" max="480" step="5"
                           value="30" class="k-input">
                </div>
                <div>
                    <label for="appointment_doctor" class="k-label">Médecin</label>
                    <select id="appointment_doctor" name="doctor_id" class="k-select">
                        <option value="">Non attribué</option>
                        @if ($patient->attendingDoctor)
                            <option value="{{ $patient->attendingDoctor->id }}" selected>
                                {{ $patient->attendingDoctor->displayName() }} (médecin traitant)
                            </option>
                        @endif
                    </select>
                </div>
                <div>
                    <label for="appointment_reason" class="k-label">Motif</label>
                    <input id="appointment_reason" name="reason" type="text" maxlength="255" class="k-input"
                           placeholder="Consultation de suivi">
                </div>
                <label class="flex items-start gap-2 text-sm text-ink-600">
                    <input type="checkbox" name="reminder_enabled" value="1" checked
                           class="mt-0.5 h-4 w-4 rounded border-ink-300 text-clinic-600">
                    <span>
                        Envoyer un rappel SMS la veille
                        @if (! \Keneya\Dme\Support\PhoneNumber::isSendable($patient->phone))
                            <span class="block text-xs text-amber-700">
                                Aucun numéro exploitable pour ce patient : le SMS ne sera pas émis.
                            </span>
                        @endif
                    </span>
                </label>
                <button type="submit" class="k-btn-primary w-full">Programmer</button>
            </form>
        </section>
    @endcan
</div>
