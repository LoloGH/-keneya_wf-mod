<section class="card" wire:poll.{{ config('keneya.poll_interval') }}>
    <div class="card__head">
        <h2 class="card__title">File d'attente — {{ $service->name }}</h2>

        <button type="button" class="btn btn--primary" wire:click="callNext" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="callNext">Appeler le suivant</span>
            <span wire:loading wire:target="callNext">Appel…</span>
        </button>
    </div>

    @if ($queue->isEmpty())
        <p class="empty">Aucun patient dans cette file.</p>
    @else
        <ul class="queue">
            @foreach ($queue as $patient)
                <li class="queue__item queue__item--{{ $patient->status }}">
                    <button type="button" class="queue__patient" wire:click="showHistory({{ $patient->id }})">
                        <span class="queue__token">{{ $patient->token }}</span>
                        <span class="queue__identity">
                            <strong>{{ $patient->name }}</strong>
                            <span class="mono">{{ $patient->patient_code }}</span>
                            <span>{{ $patient->age }} ans — {{ $patient->gender }}</span>
                        </span>
                        <span class="badge badge--{{ $patient->status }}">{{ $patient->statusLabel() }}</span>
                    </button>

                    <button type="button" class="btn btn--secondary"
                            wire:click="startReferral({{ $patient->id }})">
                        Envoyer vers un service
                    </button>
                </li>

                @if ($referringPatientId === $patient->id)
                    <li class="referral-form">
                        <form wire:submit="sendReferral" class="form">
                            <p class="referral-form__title">Renvoyer {{ $patient->name }}</p>

                            <div class="field">
                                <label for="to-service-{{ $patient->id }}">Service destinataire</label>
                                <select id="to-service-{{ $patient->id }}" wire:model="toServiceId">
                                    <option value="">— Choisir un service —</option>
                                    @foreach ($otherServices as $other)
                                        <option value="{{ $other->id }}">
                                            {{ $other->name }}
                                            @if ($other->doctors->isNotEmpty())
                                                ({{ $other->doctors->map(fn ($d) => $d->user?->name)->filter()->join(', ') }})
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('toServiceId') <p class="field__error">{{ $message }}</p> @enderror
                            </div>

                            <div class="field">
                                <label for="instructions-{{ $patient->id }}">Instructions</label>
                                <textarea id="instructions-{{ $patient->id }}" rows="3" wire:model="instructions"
                                          placeholder="Examen demande, precisions cliniques…"></textarea>
                                @error('instructions') <p class="field__error">{{ $message }}</p> @enderror
                            </div>

                            <div class="btn-row">
                                <button type="submit" class="btn btn--primary" wire:loading.attr="disabled">
                                    Envoyer
                                </button>
                                <button type="button" class="btn btn--ghost" wire:click="cancelReferral">
                                    Annuler
                                </button>
                            </div>
                        </form>
                    </li>
                @endif
            @endforeach
        </ul>
    @endif
</section>
