<section class="card" wire:poll.{{ config('keneya.poll_interval') }}>
    <div class="card__head">
        <h2 class="card__title">{{ $service->name }}</h2>
        <div class="btn-row">
            <span class="cash-total">Encaisse aujourd'hui : <strong>{{ number_format($todayTotal, 0, ',', ' ') }} FCFA</strong></span>
            <button type="button" class="btn btn--primary" wire:click="callNext" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="callNext">Appeler le suivant</span>
                <span wire:loading wire:target="callNext">Appel…</span>
            </button>
        </div>
    </div>

    @if ($queue->isEmpty())
        <p class="empty">Aucun patient dans cette file.</p>
    @else
        <ul class="queue">
            @foreach ($queue as $visit)
                <li class="queue__item queue__item--{{ $visit->status }}">
                    <span class="queue__patient">
                        <span class="queue__token">{{ $visit->token }}</span>
                        <span class="queue__identity">
                            <strong>{{ $visit->patient->name }}</strong>
                            <span class="mono">{{ $visit->patient->patient_code }}</span>
                            @if ($visit->pendingNextService)
                                <span>Puis : {{ $visit->pendingNextService->name }}</span>
                            @endif
                            {{-- L'acte et son tarif, avant meme d'appeler le
                                 patient : le caissier n'a plus a demander ce
                                 qui est facture (v3.2.8, point 3). --}}
                            @if ($actes[$visit->id] ?? null)
                                <span class="queue__acte">
                                    {{ $actes[$visit->id]->name }}
                                    <strong>{{ $actes[$visit->id]->formattedPrice() }}</strong>
                                </span>
                            @endif
                        </span>
                        <span class="badge badge--{{ $visit->status }}">{{ $visit->statusLabel() }}</span>
                    </span>

                    @if ($visit->awaitsPayment() && ! $visit->isClosed())
                        <button type="button" class="btn btn--secondary" wire:click="startPayment({{ $visit->id }})">
                            Encaisser et orienter
                        </button>
                    @endif
                </li>

                @if ($payingVisitId === $visit->id)
                    <li class="referral-form">
                        <form wire:submit="confirmAndRoute" class="form">
                            <p class="referral-form__title">
                                {{ $visit->patient->name }} — orientation vers
                                {{ $visit->pendingNextService?->name ?? '—' }}
                            </p>

                            @if ($actes[$visit->id] ?? null)
                                <p class="acte-facture">
                                    <span>Acte facture</span>
                                    <strong>{{ $actes[$visit->id]->name }}</strong>
                                    <span class="acte-facture__tarif">{{ $actes[$visit->id]->formattedPrice() }}</span>
                                </p>
                            @else
                                <p class="hint">
                                    Aucun tarif du catalogue ne correspond a cette visite :
                                    le montant reste a saisir. Signalez-le a l'administration
                                    pour que l'acte soit ajoute aux tarifs.
                                </p>
                            @endif

                            <div class="field">
                                <label for="amount-{{ $visit->id }}">Montant encaisse <span class="field__hint">en FCFA</span></label>
                                <input id="amount-{{ $visit->id }}" type="number" inputmode="numeric"
                                       min="1" step="1" wire:model.live="amount"
                                       @if ($catalogPrice !== null && ! $overridePrice) readonly @endif>
                                @error('amount') <p class="field__error">{{ $message }}</p> @enderror
                            </div>

                            {{-- La derogation est un geste delibere : la case
                                 doit etre cochee pour que le montant s'ouvre a
                                 la saisie, et le motif devient alors obligatoire. --}}
                            @if ($catalogPrice !== null)
                                <div class="field field--check">
                                    <label>
                                        <input type="checkbox" wire:model.live="overridePrice">
                                        Encaisser un montant different du tarif
                                    </label>
                                </div>

                                @if ($overridePrice)
                                    <div class="field">
                                        <label for="override-{{ $visit->id }}">Motif de la derogation</label>
                                        <input id="override-{{ $visit->id }}" type="text" maxlength="255"
                                               wire:model="overrideReason"
                                               placeholder="Ex. : indigent, tarif negocie par la direction">
                                        @error('overrideReason') <p class="field__error">{{ $message }}</p> @enderror
                                        <p class="hint">
                                            Le motif est inscrit au journal d'audit avec le tarif attendu.
                                        </p>
                                    </div>

                                    <button type="button" class="btn btn--ghost" wire:click="restoreCatalogPrice">
                                        Revenir au tarif ({{ number_format($catalogPrice, 0, ',', ' ') }} FCFA)
                                    </button>
                                @endif
                            @endif

                            <div class="btn-row">
                                <button type="submit" class="btn btn--close" wire:loading.attr="disabled">
                                    Confirmer et orienter
                                </button>
                                <button type="button" class="btn btn--ghost" wire:click="cancel">Annuler</button>
                            </div>
                        </form>
                    </li>
                @endif
            @endforeach
        </ul>
    @endif

    @if ($todayPayments->isNotEmpty())
        <h3 class="card__subtitle">Mes derniers encaissements</h3>
        <ul class="payments">
            @foreach ($todayPayments as $payment)
                <li>
                    <strong>{{ $payment->formattedAmount() }}</strong>
                    — {{ $payment->patient->name }}
                    <span>{{ $payment->subjectLabel() }}</span>
                    @if ($payment->service) <span>({{ $payment->service->name }})</span> @endif
                    @if ($payment->isOverridden())
                        <span class="badge badge--failed">derogation</span>
                    @endif
                    <time>{{ $payment->created_at->format('H:i') }}</time>
                    {{-- Le recu imprimable est une capacite optionnelle du
                         type de caissier (v3.2.2). --}}
                    @if (auth()->user()->hasCapability(\App\Models\StaffType::CAP_PRINT_TICKET))
                        <a href="{{ route('caisse.receipt', $payment) }}" target="_blank" class="attachments__link">Reçu</a>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>
