<section class="card">
    <h2 class="card__title">Mes patients</h2>
    <p class="hint">
        Tous les patients que vous avez pris en charge, dossiers clotures compris —
        la cloture retire le patient de la file, jamais de votre historique.
    </p>

    <div class="form form--inline-wrap">
        <div class="field">
            <label for="my-patients-search">Rechercher</label>
            <input id="my-patients-search" type="search" wire:model.live.debounce.400ms="search"
                   placeholder="Nom ou numero de dossier…">
        </div>

        <label class="field field--inline">
            <input type="checkbox" wire:model.live="includeClosed">
            <span>Inclure les dossiers clotures</span>
        </label>
    </div>

    @if ($patients->isEmpty())
        <p class="empty">Aucun patient ne correspond.</p>
    @else
        <ul class="my-patients">
            @foreach ($patients as $patient)
                <li class="my-patients__item">
                    <button type="button" class="my-patients__identity" wire:click="showRecord({{ $patient->id }})">
                        <strong>{{ $patient->name }}</strong>
                        <span class="mono">{{ $patient->patient_code }}</span>
                        <span>{{ $patient->age }} ans — {{ $patient->gender }}</span>
                    </button>

                    <ul class="my-patients__visits">
                        @foreach ($patient->visits as $visit)
                            <li>
                                <span class="badge badge--{{ $visit->status }}">{{ $visit->statusLabel() }}</span>
                                {{ $visit->service->name }}
                                <time>{{ $visit->opened_at?->format('d/m/Y') }}</time>
                            </li>
                        @endforeach
                    </ul>
                </li>
            @endforeach
        </ul>

        {{ $patients->links() }}
    @endif
</section>
