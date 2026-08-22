<section class="card">
    <div class="card__head">
        <h2 class="card__title">Caisse Ticket</h2>
        <span class="cash-total">Total du jour : <strong>{{ number_format($todayTotal, 0, ',', ' ') }} FCFA</strong></span>
    </div>

    @if ($visits->isEmpty())
        <p class="empty">Aucun passage aujourd'hui : aucun ticket a encaisser.</p>
    @else
        <form wire:submit="record" class="form form--inline-wrap">
            <div class="field">
                <label for="cash-visit">Patient</label>
                <select id="cash-visit" wire:model="visitId">
                    <option value="">— Choisir un patient —</option>
                    @foreach ($visits as $visit)
                        <option value="{{ $visit->id }}">
                            {{ $visit->patient->name }} ({{ $visit->patient->patient_code }})
                            — {{ $visit->service->name }}, n° {{ $visit->token }}
                        </option>
                    @endforeach
                </select>
                @error('visitId') <p class="field__error">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="cash-amount">Montant <span class="field__hint">en FCFA</span></label>
                <input id="cash-amount" type="number" inputmode="numeric" min="1" step="1" wire:model="amount">
                @error('amount') <p class="field__error">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="btn btn--primary">Encaisser le ticket</button>
        </form>
    @endif

    @if ($todayPayments->isNotEmpty())
        <h3 class="card__subtitle">Derniers encaissements</h3>
        <ul class="payments">
            @foreach ($todayPayments as $payment)
                <li>
                    <strong>{{ $payment->formattedAmount() }}</strong>
                    — {{ $payment->patient->name }}
                    <span class="badge badge--{{ $payment->status }}">{{ $payment->statusLabel() }}</span>
                    <time>{{ $payment->created_at->format('H:i') }}</time>
                </li>
            @endforeach
        </ul>
    @endif
</section>
