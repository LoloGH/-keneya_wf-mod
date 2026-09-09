<x-card title="Passages du jour" icon="document" :poll="config('keneya.poll_interval')">

    <div class="field">
        <label for="visits-search" class="sr-only">Rechercher</label>
        <input id="visits-search" type="search" wire:model.live.debounce.400ms="search"
               placeholder="Rechercher un nom, un code, un telephone...">
    </div>

    <h3 class="card__subtitle">Patients ({{ $visits->count() }})</h3>

    @if ($visits->isEmpty())
        <p class="empty">Aucun passage enregistre aujourd'hui.</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>N&deg; patient</th><th>Nom</th><th>Service</th>
                        <th>Ticket</th><th>Statut</th><th>Heure</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($visits as $visit)
                        <tr>
                            <td class="mono">{{ $visit->patient->patient_code }}</td>
                            <td>{{ $visit->patient->name }}</td>
                            <td>{{ $visit->service->name }}</td>
                            <td class="mono">{{ $visit->token }}</td>
                            <td><span class="badge badge--{{ $visit->status }}">{{ $visit->statusLabel() }}</span></td>
                            <td>{{ $visit->opened_at?->format('H:i') }}</td>
                            <td>
                                {{-- Reimpression si le ticket papier s'est perdu. --}}
                                <a href="{{ route('reception.ticket.patient', $visit) }}" target="_blank"
                                   class="attachments__link">Imprimer</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h3 class="card__subtitle">Visiteurs ({{ $visitors->count() }})</h3>

    @if ($visitors->isEmpty())
        <p class="empty">Aucun visiteur enregistre aujourd'hui.</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Fiche</th><th>Nom</th><th>Visite a</th><th>Service</th><th>Ticket</th><th>Heure</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($visitors as $visitor)
                        <tr>
                            <td class="mono">{{ $visitor->visitor_code }}</td>
                            <td>{{ $visitor->name }}</td>
                            <td>{{ $visitor->patient?->name ?? '-' }}</td>
                            <td>{{ $visitor->service->name }}</td>
                            <td class="mono">{{ $visitor->token ?? '-' }}</td>
                            <td>{{ $visitor->created_at->format('H:i') }}</td>
                            <td>
                                <a href="{{ route('reception.ticket.visitor', $visitor) }}" target="_blank"
                                   class="attachments__link">Imprimer</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-card>
