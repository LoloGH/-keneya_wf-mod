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
            @foreach ($queue as $visit)
                @php $pendingReferral = $visit->referrals->firstWhere('status', \App\Models\Referral::STATUS_PENDING); @endphp

                <li class="queue__item queue__item--{{ $visit->status }}">
                    <button type="button" class="queue__patient" wire:click="showRecord({{ $visit->patient_id }})">
                        <span class="queue__token">{{ $visit->token }}</span>
                        <span class="queue__identity">
                            <strong>{{ $visit->patient->name }}</strong>
                            <span class="mono">{{ $visit->patient->patient_code }}</span>
                            <span>{{ $visit->patient->age }} ans — {{ $visit->patient->gender }}</span>
                        </span>
                        <span class="badge badge--{{ $visit->status }}">{{ $visit->statusLabel() }}</span>
                    </button>

                    <div class="btn-row">
                        @unless ($visit->isClosed())
                            <button type="button" class="btn btn--secondary"
                                    wire:click="startReferral({{ $visit->id }})">
                                Envoyer vers un service
                            </button>

                            @if ($visit->status === \App\Models\Visit::STATUS_CALLED)
                                @if ($pendingReferral)
                                    <span class="hint hint--blocking">
                                        En attente du resultat de {{ $pendingReferral->toService->name ?? 'un renvoi' }}
                                        — cloture impossible.
                                    </span>
                                @else
                                    <button type="button" class="btn btn--close"
                                            wire:click="closeVisit({{ $visit->id }})"
                                            wire:confirm="Cloturer definitivement le dossier de {{ $visit->patient->name }} ?">
                                        Cloturer le dossier
                                    </button>
                                @endif
                            @endif
                        @endunless
                    </div>
                </li>

                @if ($referringVisitId === $visit->id)
                    <li class="referral-form">
                        <form wire:submit="sendReferral" class="form">
                            <p class="referral-form__title">Renvoyer {{ $visit->patient->name }}</p>

                            <div class="field">
                                <label for="to-service-{{ $visit->id }}">Service destinataire</label>
                                <select id="to-service-{{ $visit->id }}" wire:model="toServiceId">
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
                                <label for="instructions-{{ $visit->id }}">Instructions</label>
                                <textarea id="instructions-{{ $visit->id }}" rows="3" wire:model="instructions"
                                          placeholder="Examen demande, precisions cliniques…"></textarea>
                                @error('instructions') <p class="field__error">{{ $message }}</p> @enderror
                            </div>

                            <div class="btn-row">
                                <button type="submit" class="btn btn--primary" wire:loading.attr="disabled">Envoyer</button>
                                <button type="button" class="btn btn--ghost" wire:click="cancelReferral">Annuler</button>
                            </div>
                        </form>
                    </li>
                @endif
            @endforeach
        </ul>
    @endif
</section>
