<section class="card">
    <h2 class="card__title">Enregistrer un patient</h2>

    <form wire:submit="save" class="form">
        <div class="field">
            <label for="patient-name">Nom complet</label>
            <input id="patient-name" type="text" wire:model="name" autocomplete="off">
            @error('name') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field-row">
            <div class="field">
                <label for="patient-age">Age</label>
                <input id="patient-age" type="number" inputmode="numeric" min="0" max="130" wire:model="age">
                @error('age') <p class="field__error">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="patient-gender">Sexe</label>
                <select id="patient-gender" wire:model="gender">
                    <option value="Homme">Homme</option>
                    <option value="Femme">Femme</option>
                </select>
                @error('gender') <p class="field__error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="field">
            <label for="patient-mobile">Telephone</label>
            <input id="patient-mobile" type="tel" inputmode="tel" wire:model="mobile" placeholder="76 00 00 00">
            @error('mobile') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="patient-crno">Numero de dossier papier <span class="field__hint">(facultatif)</span></label>
            <input id="patient-crno" type="text" wire:model="crno">
            @error('crno') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="patient-service">Service</label>
            <select id="patient-service" wire:model="service_id">
                <option value="">— Choisir un service —</option>
                @foreach ($services as $service)
                    <option value="{{ $service->id }}">{{ $service->name }}</option>
                @endforeach
            </select>
            @error('service_id') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="btn btn--primary btn--block" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="save">Enregistrer le patient</span>
            <span wire:loading wire:target="save">Enregistrement…</span>
        </button>
    </form>

    @if ($lastRegistered)
        <div class="ticket" role="status">
            <p class="ticket__label">Dossier</p>
            <p class="ticket__code">{{ $lastRegistered['patient_code'] }}</p>
            <p class="ticket__meta">
                {{ $lastRegistered['name'] }} — {{ $lastRegistered['service'] }},
                ticket n° {{ $lastRegistered['token'] }}
            </p>
        </div>
    @endif
</section>
