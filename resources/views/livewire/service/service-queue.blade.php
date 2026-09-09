<x-card title="File d'attente - {{ $service->name }}" icon="file" :poll="config('keneya.poll_interval')">
    <x-slot:actions>

        <button type="button" class="btn btn--primary" wire:click="callNext" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="callNext">Appeler le suivant</span>
            <span wire:loading wire:target="callNext">Appel...</span>
        </button>
    </x-slot:actions>

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
                            <span>{{ $visit->patient->age }} ans - {{ $visit->patient->gender }}</span>
                        </span>
                        <span class="badge badge--{{ $visit->status }}">{{ $visit->statusLabel() }}</span>
                    </button>

                    <div class="btn-row">
                        @unless ($visit->isClosed())
                            <button type="button" class="btn btn--ghost btn--small"
                                    wire:click="startReferral({{ $visit->id }})">
                                Envoyer vers un service
                            </button>

                            @if ($visit->status === \App\Models\Visit::STATUS_CALLED)
                                @if ($pendingReferral)
                                    <span class="hint hint--blocking">
                                        En attente du resultat de {{ $pendingReferral->toService->name ?? 'un renvoi' }},
                                        cloture impossible.
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
                                <select id="to-service-{{ $visit->id }}" wire:model.live="toServiceId">
                                    <option value="">Choisir un service</option>
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

                            {{-- L'acte precis, et non le service en general
                                 (v3.2.8, point 3) : c'est lui qui porte le
                                 tarif, et il voyage avec la visite jusqu'a la
                                 caisse, le caissier n'aura plus a demander. --}}
                            @if ($actes->isNotEmpty())
                                <div class="field">
                                    <label for="acte-{{ $visit->id }}">Acte demande</label>
                                    <select id="acte-{{ $visit->id }}" wire:model="billableItemId">
                                        <option value="">Aucun acte facturable</option>
                                        @foreach ($actes as $acte)
                                            <option value="{{ $acte->id }}">{{ $acte->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('billableItemId') <p class="field__error">{{ $message }}</p> @enderror
                                </div>
                            @elseif ($toServiceId)
                                <p class="hint">
                                    Aucun tarif n'est defini pour ce service : le caissier
                                    devra saisir le montant. Demandez a l'administration
                                    d'ajouter les actes a la section « Tarifs ».
                                </p>
                            @endif

                            {{-- La demande d'examen, quand la destination en
                                 realise une (v3.3.1). Elle part avec le
                                 patient : le technicien recoit le patient et
                                 ce qu'on lui demande, d'un seul geste. --}}
                            @include('partials.examination-request', [
                                'examKind' => $examKind,
                                'suffixe' => 'renvoi-'.$visit->id,
                            ])

                            <div class="field">
                                <label for="instructions-{{ $visit->id }}">Instructions</label>
                                <textarea id="instructions-{{ $visit->id }}" rows="3" wire:model="instructions"
                                          placeholder="Examen demande, precisions cliniques..."></textarea>
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
</x-card>
