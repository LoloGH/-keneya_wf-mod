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

                    {{-- Rendez-vous et pieces jointes depuis le dossier, pour
                         tout patient de la liste — pas seulement celui qu'on
                         est en train de consulter. --}}
                    <div class="btn-row">
                        <button type="button" class="btn btn--secondary"
                                wire:click="startAppointment({{ $patient->id }})">
                            Donner un rendez-vous
                        </button>
                        <button type="button" class="btn btn--secondary"
                                wire:click="startAttachment({{ $patient->id }})">
                            Ajouter une piece jointe
                        </button>
                        @if ($patient->mobile)
                            <button type="button" class="btn btn--ghost"
                                    wire:click="sendPortalLink({{ $patient->id }})">
                                Envoyer le lien de mes documents
                            </button>
                        @endif
                    </div>

                    @if ($appointmentPatientId === $patient->id)
                        <form wire:submit="saveAppointment" class="form my-patients__form">
                            <div class="field">
                                <label for="rdv-{{ $patient->id }}">Date et heure du rendez-vous</label>
                                <input id="rdv-{{ $patient->id }}" type="datetime-local" wire:model="appointmentAt">
                                @error('appointmentAt') <p class="field__error">{{ $message }}</p> @enderror
                            </div>
                            <div class="btn-row">
                                <button type="submit" class="btn btn--primary">Fixer le rendez-vous</button>
                                <button type="button" class="btn btn--ghost" wire:click="cancelAppointment">Annuler</button>
                            </div>
                        </form>
                    @endif

                    @if ($attachmentPatientId === $patient->id)
                        <form wire:submit="saveAttachment" class="form my-patients__form">
                            <div class="field">
                                <label for="pj-{{ $patient->id }}">
                                    Fichiers
                                    <span class="field__hint">PDF, JPG ou PNG — 10 Mo maximum, 5 fichiers</span>
                                </label>
                                <input id="pj-{{ $patient->id }}" type="file" multiple
                                       accept=".pdf,.jpg,.jpeg,.png" wire:model="files">
                                @error('files') <p class="field__error">{{ $message }}</p> @enderror
                                @error('files.*') <p class="field__error">{{ $message }}</p> @enderror
                                <p class="hint" wire:loading wire:target="files">Televersement en cours…</p>
                            </div>
                            <div class="btn-row">
                                <button type="submit" class="btn btn--primary"
                                        wire:loading.attr="disabled" wire:target="saveAttachment">
                                    Ajouter au dossier
                                </button>
                                <button type="button" class="btn btn--ghost" wire:click="cancelAttachment">Annuler</button>
                            </div>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>

        {{ $patients->links() }}
    @endif
</section>
