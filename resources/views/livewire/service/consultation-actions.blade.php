<section class="card">
    <h2 class="card__title">Fin de consultation</h2>

    @if ($visits->isEmpty())
        <p class="empty">Aucun patient appele : appelez un patient pour encaisser un acte, etablir une ordonnance ou fixer un rendez-vous.</p>
    @else
        <div class="field">
            <label for="consultation-visit">Patient</label>
            <select id="consultation-visit" wire:model.live="visitId">
                <option value="">— Choisir un patient appele —</option>
                @foreach ($visits as $visit)
                    <option value="{{ $visit->id }}">
                        n° {{ $visit->token }} — {{ $visit->patient->name }} ({{ $visit->patient->patient_code }})
                    </option>
                @endforeach
            </select>
            @error('visitId') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="tabs" role="tablist">
            <button type="button" role="tab" class="tabs__tab @if ($tab === 'caisse') tabs__tab--active @endif"
                    wire:click="selectTab('caisse')">Caisse Services</button>
            <button type="button" role="tab" class="tabs__tab @if ($tab === 'ordonnance') tabs__tab--active @endif"
                    wire:click="selectTab('ordonnance')">Ordonnance</button>
            <button type="button" role="tab" class="tabs__tab @if ($tab === 'rendez-vous') tabs__tab--active @endif"
                    wire:click="selectTab('rendez-vous')">Rendez-vous</button>
        </div>

        @if ($tab === 'caisse')
            <form wire:submit="recordPayment" class="form">
                <div class="field">
                    <label for="payment-amount">Montant de l'acte <span class="field__hint">en FCFA</span></label>
                    <input id="payment-amount" type="number" inputmode="numeric" min="1" step="1" wire:model="amount">
                    @error('amount') <p class="field__error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn btn--primary">Encaisser</button>
            </form>

            @if ($recentPayments->isNotEmpty())
                <h3 class="card__subtitle">Encaissements du jour</h3>
                <ul class="payments">
                    @foreach ($recentPayments as $payment)
                        <li>
                            <strong>{{ $payment->formattedAmount() }}</strong>
                            — {{ $payment->patient->name }}
                            <time>{{ $payment->created_at->format('H:i') }}</time>
                        </li>
                    @endforeach
                </ul>
            @endif
        @elseif ($tab === 'ordonnance')
            <form wire:submit="savePrescription" class="form">
                <div class="field">
                    <label for="prescription-content">Ordonnance</label>
                    <textarea id="prescription-content" rows="6" wire:model="prescription"
                              placeholder="Medicaments, posologie, duree…"></textarea>
                    @error('prescription') <p class="field__error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn btn--primary">Enregistrer l'ordonnance</button>
            </form>
        @else
            <form wire:submit="saveAppointment" class="form">
                <div class="field">
                    <label for="appointment-at">Date et heure du prochain rendez-vous</label>
                    <input id="appointment-at" type="datetime-local" wire:model="appointmentAt">
                    @error('appointmentAt') <p class="field__error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn btn--primary">Fixer le rendez-vous</button>
            </form>
        @endif
    @endif
</section>
