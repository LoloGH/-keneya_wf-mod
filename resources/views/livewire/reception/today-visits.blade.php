<section class="card" wire:poll.{{ config('keneya.poll_interval') }}>
    <h2 class="card__title">Passages du jour</h2>

    <div class="field">
        <label for="visits-search" class="sr-only">Rechercher</label>
        <input id="visits-search" type="search" wire:model.live.debounce.400ms="search"
               placeholder="Rechercher un nom, un code, un telephone…">
    </div>

    <h3 class="card__subtitle">Patients ({{ $patients->count() }})</h3>

    @if ($patients->isEmpty())
        <p class="empty">Aucun patient enregistre aujourd'hui.</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Dossier</th>
                        <th>Nom</th>
                        <th>Service</th>
                        <th>Ticket</th>
                        <th>Statut</th>
                        <th>Heure</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($patients as $patient)
                        <tr>
                            <td class="mono">{{ $patient->patient_code }}</td>
                            <td>{{ $patient->name }}</td>
                            <td>{{ $patient->service->name }}</td>
                            <td class="mono">{{ $patient->token }}</td>
                            <td>
                                <span class="badge badge--{{ $patient->status }}">{{ $patient->statusLabel() }}</span>
                            </td>
                            <td>{{ $patient->created_at->format('H:i') }}</td>
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
                    <tr>
                        <th>Fiche</th>
                        <th>Nom</th>
                        <th>Service</th>
                        <th>Motif</th>
                        <th>Heure</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($visitors as $visitor)
                        <tr>
                            <td class="mono">{{ $visitor->visitor_code }}</td>
                            <td>{{ $visitor->name }}</td>
                            <td>{{ $visitor->service->name }}</td>
                            <td>{{ $visitor->reason ?: '—' }}</td>
                            <td>{{ $visitor->created_at->format('H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
