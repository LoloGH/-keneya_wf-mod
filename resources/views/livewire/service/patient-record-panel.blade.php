<aside class="card record">
    @if (! $patient)
        <h2 class="card__title">Dossier patient</h2>
        <p class="empty">Selectionnez un patient dans la file pour ouvrir son dossier.</p>
    @else
        <div class="card__head">
            <h2 class="card__title">Dossier {{ $patient->patient_code }}</h2>
            <button type="button" class="btn btn--ghost" wire:click="close">Fermer</button>
        </div>

        <dl class="record__identity">
            <div><dt>Nom</dt><dd>{{ $patient->name }}</dd></div>
            <div><dt>Age</dt><dd>{{ $patient->age }} ans</dd></div>
            <div><dt>Sexe</dt><dd>{{ $patient->gender }}</dd></div>
            <div><dt>Telephone</dt><dd>{{ $patient->mobile }}</dd></div>
            <div><dt>Dossier papier</dt><dd>{{ $patient->crno ?: '—' }}</dd></div>
            <div><dt>Service actuel</dt><dd>{{ $patient->service->name }}</dd></div>
            <div><dt>Ticket</dt><dd class="mono">{{ $patient->token }}</dd></div>
        </dl>

        <h3 class="card__subtitle">Parcours</h3>

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
                        @if ($entry->doctor)
                            — {{ $entry->doctor->name() }}
                        @endif
                    </p>
                </li>
            @endforeach
        </ol>
    @endif
</aside>
