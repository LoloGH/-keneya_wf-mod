<x-card title="Enregistrer un visiteur" icon="personnel">

    <form wire:submit="save" class="form">
        <div class="field">
            <label for="visitor-name">Nom complet</label>
            <input id="visitor-name" type="text" wire:model="name" autocomplete="off">
            @error('name') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="visitor-mobile">Telephone <span class="field__hint">(facultatif)</span></label>
            <input id="visitor-mobile" type="tel" inputmode="tel" wire:model="mobile">
            @error('mobile') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        {{-- Un accompagnateur revient : l'etablissement doit pouvoir le
             reconnaitre. Facultatif, comme pour un patient. --}}
        <div class="field">
            <label for="visitor-id-card">
                N&deg; de la carte d'identite <span class="field__hint">(facultatif)</span>
            </label>
            <input id="visitor-id-card" type="text" wire:model="idCardNumber" autocomplete="off">
            @error('idCardNumber') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="visitor-service">Service visite</label>
            <select id="visitor-service" wire:model="service_id">
                <option value="">Choisir un service</option>
                @foreach ($services as $service)
                    <option value="{{ $service->id }}">{{ $service->name }}</option>
                @endforeach
            </select>
            @error('service_id') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        {{-- On rend visite a quelqu'un : obligatoire des qu'un service
             clinique est choisi, facultatif pour une demarche administrative. --}}
        <div class="field">
            <label for="visitor-patient">
                Patient visite
                <span class="field__hint">{{ $this->requiresPatient() ? '(obligatoire)' : '(facultatif)' }}</span>
            </label>

            @if ($selectedPatient)
                <p class="lookup__chosen">
                    <strong>{{ $selectedPatient->name }}</strong>
                    <span class="mono">{{ $selectedPatient->patient_code }}</span>
                    <button type="button" class="btn btn--ghost" wire:click="clearPatient">Changer</button>
                </p>
            @else
                <input id="visitor-patient" type="search" wire:model.live.debounce.400ms="patientSearch"
                       placeholder="Nom ou N&deg; du patient...">

                @if ($matches->isNotEmpty())
                    <ul class="lookup">
                        @foreach ($matches as $match)
                            <li class="lookup__item">
                                <span class="lookup__identity">
                                    <strong>{{ $match->name }}</strong>
                                    <span class="mono">{{ $match->patient_code }}</span>
                                </span>
                                <button type="button" class="btn btn--ghost"
                                        wire:click="selectPatient({{ $match->id }})">Choisir</button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endif
            @error('patient_id') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="visitor-reason">Motif <span class="field__hint">(facultatif)</span></label>
            <input id="visitor-reason" type="text" wire:model="reason">
            @error('reason') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="btn btn--secondary btn--block" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="save">Enregistrer le visiteur</span>
            <span wire:loading wire:target="save">Enregistrement...</span>
        </button>
    </form>

    @if ($lastRegistered)
        <div class="ticket ticket--muted" role="status">
            <p class="ticket__label">Fiche visiteur</p>
            <p class="ticket__code">{{ $lastRegistered['visitor_code'] }}</p>
            <p class="ticket__meta">
                {{ $lastRegistered['name'] }} - {{ $lastRegistered['service'] }},
                ticket n° {{ $lastRegistered['token'] }}
            </p>
            @if ($lastRegistered['visited'] ?? null)
                <p class="ticket__meta">Visite a {{ $lastRegistered['visited'] }}</p>
            @endif

            <div class="btn-row btn-row--centered">
                <a href="{{ route('reception.ticket.visitor', $lastRegistered['id']) }}"
                   target="_blank" class="btn btn--primary">Imprimer le ticket</a>
            </div>
        </div>
    @endif
</x-card>
