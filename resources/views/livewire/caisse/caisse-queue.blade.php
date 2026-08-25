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

                            <div class="field">
                                <label for="amount-{{ $visit->id }}">Montant encaisse <span class="field__hint">en FCFA</span></label>
                                <input id="amount-{{ $visit->id }}" type="number" inputmode="numeric"
                                       min="1" step="1" wire:model="amount">
                                @error('amount') <p class="field__error">{{ $message }}</p> @enderror
                            </div>

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
                    @if ($payment->service) <span>({{ $payment->service->name }})</span> @endif
                    <time>{{ $payment->created_at->format('H:i') }}</time>
                    <a href="{{ route('caisse.receipt', $payment) }}" target="_blank" class="attachments__link">Reçu</a>
                </li>
            @endforeach
        </ul>
    @endif
</section>
