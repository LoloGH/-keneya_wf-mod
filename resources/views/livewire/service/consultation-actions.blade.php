<section class="card">
    <h2 class="card__title">Fin de consultation</h2>

    @if ($visits->isEmpty())
        <p class="empty">Appelez un patient pour encaisser un acte, etablir une ordonnance ou fixer un rendez-vous.</p>
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
            {{-- L'ordonnance s'ecrit ligne par ligne, numerotee : c'est ainsi
                 qu'elle sera lue au comptoir de la pharmacie. --}}
            <form wire:submit="savePrescription" class="form">
                <ol class="ordo">
                    @foreach ($prescriptionLines as $index => $ligne)
                        <li class="ordo__line" wire:key="ordo-{{ $index }}">
                            <span class="ordo__rank" aria-hidden="true">{{ $index + 1 }}</span>

                            <div class="ordo__fields">
                                <div class="field">
                                    <label for="ordo-med-{{ $index }}">Medicament</label>
                                    <input id="ordo-med-{{ $index }}" type="text"
                                           wire:model="prescriptionLines.{{ $index }}.medicament"
                                           placeholder="Paracetamol 500 mg">
                                    @error("prescriptionLines.$index.medicament")
                                        <p class="field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="field">
                                    <label for="ordo-pos-{{ $index }}">Posologie</label>
                                    <input id="ordo-pos-{{ $index }}" type="text"
                                           wire:model="prescriptionLines.{{ $index }}.posologie"
                                           placeholder="1 comprime matin et soir">
                                    @error("prescriptionLines.$index.posologie")
                                        <p class="field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="field field--court">
                                    <label for="ordo-dur-{{ $index }}">Duree</label>
                                    <input id="ordo-dur-{{ $index }}" type="text"
                                           wire:model="prescriptionLines.{{ $index }}.duree"
                                           placeholder="5 jours">
                                    @error("prescriptionLines.$index.duree")
                                        <p class="field__error">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            {{-- Toujours presente, meme sur la premiere ligne :
                                 une action qui disparait laisse l'utilisateur
                                 devant une case vide. La derniere ligne se vide
                                 au lieu de disparaitre. --}}
                            <button type="button" class="btn btn--ghost btn--icon btn--icon-danger ordo__remove"
                                    wire:click="removePrescriptionLine({{ $index }})"
                                    aria-label="Retirer la ligne {{ $index + 1 }}"
                                    title="Retirer cette ligne">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                    <path d="M5 12h14" />
                                </svg>
                            </button>
                        </li>
                    @endforeach
                </ol>

                @error('prescriptionLines') <p class="field__error">{{ $message }}</p> @enderror

                <div class="btn-row">
                    @if (count($prescriptionLines) < \App\Livewire\Service\ConsultationActions::MAX_LIGNES)
                        <button type="button" class="btn btn--ghost ordo__add" wire:click="addPrescriptionLine">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                <path d="M12 5v14M5 12h14" />
                            </svg>
                            Ajouter une ligne
                        </button>
                    @endif

                    <button type="submit" class="btn btn--primary">Enregistrer l'ordonnance</button>
                </div>
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
