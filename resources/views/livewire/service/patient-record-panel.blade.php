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
            <div><dt>Passages</dt><dd>{{ $patient->visits->count() }}</dd></div>
        </dl>

        @if ($patient->companions->isNotEmpty())
            <h3 class="card__subtitle">Accompagnateurs</h3>
            <ul class="companions">
                @foreach ($patient->companions as $companion)
                    <li>
                        <strong>{{ $companion->name }}</strong>
                        @if ($companion->relation) <span>({{ $companion->relation }})</span> @endif
                        @if ($companion->phone) <span class="mono">{{ $companion->phone }}</span> @endif
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- Un episode par visite, du plus recent au plus ancien : un patient
             deja venu doit laisser voir son passage precedent distinctement. --}}
        <h3 class="card__subtitle">Parcours par episode</h3>

        @forelse ($episodes as $episode)
            <article class="episode">
                <header class="episode__head">
                    <span class="episode__service">{{ $episode['visit']->service->name }}</span>
                    <span class="badge badge--{{ $episode['visit']->status }}">
                        {{ $episode['visit']->statusLabel() }}
                    </span>
                    <time>
                        {{ $episode['visit']->opened_at?->format('d/m/Y') }}
                        @if ($episode['visit']->closed_at)
                            → {{ $episode['visit']->closed_at->format('d/m/Y') }}
                        @endif
                    </time>
                </header>

                <ol class="timeline">
                    @forelse ($episode['entries'] as $item)
                        @include('partials.timeline-item', [
                            'item' => $item,
                            'attachmentRoute' => 'service.attachment',
                            'attachmentPrintRoute' => 'service.attachment.print',
                            'prescriptionPrintRoute' => 'service.prescription.print',
                        ])
                    @empty
                        <li class="empty">Aucun evenement enregistre pour ce passage.</li>
                    @endforelse
                </ol>

            </article>
        @empty
            <p class="empty">Aucun passage enregistre.</p>
        @endforelse

        @if ($orphans->isNotEmpty())
            <h3 class="card__subtitle">Avant la mise en place des episodes</h3>
            <ol class="timeline">
                @foreach ($orphans as $item)
                    @include('partials.timeline-item', [
                        'item' => $item,
                        'attachmentRoute' => 'service.attachment',
                        'attachmentPrintRoute' => 'service.attachment.print',
                        'prescriptionPrintRoute' => 'service.prescription.print',
                    ])
                @endforeach
            </ol>
        @endif
    @endif
</aside>
