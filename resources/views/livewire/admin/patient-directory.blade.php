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
                    <th>Dossier</th><th>Nom</th><th>Age</th>
                    <th>Dernier passage</th><th>Statut</th><th>Enregistre le</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($patients as $patient)
                    <tr>
                        <td class="mono">{{ $patient->patient_code }}</td>
                        <td>{{ $patient->name }}</td>
                        <td>{{ $patient->age }}</td>
                        <td>{{ $patient->latestVisit?->service?->name ?? '—' }}</td>
                        <td>
                            @if ($patient->latestVisit)
                                <span class="badge badge--{{ $patient->latestVisit->status }}">
                                    {{ $patient->latestVisit->statusLabel() }}
                                </span>
                            @else
                                —
                            @endif
                        </td>
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
                <div class="btn-row">
                    <button type="button" class="btn btn--ghost" wire:click="startAttachment">
                        Ajouter une piece jointe
                    </button>
                    <button type="button" class="btn btn--ghost" wire:click="closeRecord">Fermer</button>
                </div>
            </div>

            @if ($addingAttachment)
                <form wire:submit="saveAttachment" class="form my-patients__form">
                    <div class="field">
                        <label for="admin-pj">
                            Fichiers
                            <span class="field__hint">PDF, JPG ou PNG. 10 Mo maximum, 5 fichiers</span>
                        </label>
                        <input id="admin-pj" type="file" multiple wire:model="files"
                               accept=".pdf,.jpg,.jpeg,.png">
                        @error('files') <p class="field__error">{{ $message }}</p> @enderror
                        @error('files.*') <p class="field__error">{{ $message }}</p> @enderror
                    </div>

                    <div class="btn-row">
                        <button type="submit" class="btn btn--primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="files,saveAttachment">Enregistrer</span>
                            <span wire:loading wire:target="files,saveAttachment">Televersement…</span>
                        </button>
                        <button type="button" class="btn btn--ghost" wire:click="cancelAttachment">Annuler</button>
                    </div>
                </form>
            @endif

            @if ($openPatient->companions->isNotEmpty())
                <ul class="companions">
                    @foreach ($openPatient->companions as $companion)
                        <li>
                            <strong>{{ $companion->name }}</strong>
                            @if ($companion->relation) <span>({{ $companion->relation }})</span> @endif
                            @if ($companion->phone) <span class="mono">{{ $companion->phone }}</span> @endif
                        </li>
                    @endforeach
                </ul>
            @endif

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
                                'attachmentRoute' => 'admin.attachment',
                                'attachmentPrintRoute' => 'admin.attachment.print',
                                'prescriptionPrintRoute' => 'admin.prescription.print',
                            ])
                        @empty
                            <li class="empty">Aucun evenement pour ce passage.</li>
                        @endforelse
                    </ol>

                </article>
            @empty
                <p class="empty">Aucun passage enregistre.</p>
            @endforelse

            @if ($orphans->isNotEmpty())
                <h4 class="card__subtitle">Avant la mise en place des episodes</h4>
                <ol class="timeline">
                    @foreach ($orphans as $item)
                        @include('partials.timeline-item', [
                            'item' => $item,
                            'attachmentRoute' => 'admin.attachment',
                            'attachmentPrintRoute' => 'admin.attachment.print',
                            'prescriptionPrintRoute' => 'admin.prescription.print',
                        ])
                    @endforeach
                </ol>
            @endif
        </div>
    @endif
</section>
