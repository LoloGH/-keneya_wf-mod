<section class="card">
    <h2 class="card__title">Patients</h2>

    <div class="form form--inline-wrap">
        <div class="field">
            <label for="patients-search">Rechercher</label>
            <input id="patients-search" type="search" wire:model.live.debounce.400ms="search"
                   placeholder="Nom, dossier, numero papier, telephone…">
        </div>

        <div class="field">
            <label for="patients-service">Service</label>
            <select id="patients-service" wire:model.live="serviceFilter">
                <option value="">Tous les services</option>
                @foreach ($services as $service)
                    <option value="{{ $service->id }}">{{ $service->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Dossier</th>
                    <th>Nom</th>
                    <th>Age</th>
                    <th>Service actuel</th>
                    <th>Ticket</th>
                    <th>Enregistre le</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($patients as $patient)
                    <tr>
                        <td class="mono">{{ $patient->patient_code }}</td>
                        <td>{{ $patient->name }}</td>
                        <td>{{ $patient->age }}</td>
                        <td>{{ $patient->service->name }}</td>
                        <td class="mono">{{ $patient->token }}</td>
                        <td>{{ $patient->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            <button type="button" class="btn btn--ghost" wire:click="openRecord({{ $patient->id }})">
                                Ouvrir le dossier
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">Aucun patient.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $patients->links() }}

    @if ($openPatient)
        <div class="record">
            <div class="card__head">
                <h3 class="card__subtitle">Dossier {{ $openPatient->patient_code }} — {{ $openPatient->name }}</h3>
                <button type="button" class="btn btn--ghost" wire:click="closeRecord">Fermer</button>
            </div>

            <ol class="timeline">
                @foreach ($history as $entry)
                    <li class="timeline__item timeline__item--{{ $entry->type }}">
                        <p class="timeline__head">
                            <span class="timeline__type">{{ $entry->typeLabel() }}</span>
                            <time>{{ $entry->created_at->format('d/m/Y H:i') }}</time>
                        </p>
                        <p class="timeline__body">{{ $entry->description }}</p>
                        <p class="timeline__meta">
                            {{ $entry->service?->name }}
                            @if ($entry->doctor) — {{ $entry->doctor->name() }} @endif
                        </p>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif
</section>
