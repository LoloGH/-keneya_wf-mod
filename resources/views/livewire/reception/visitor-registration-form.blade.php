<section class="card">
    <h2 class="card__title">Enregistrer un visiteur</h2>

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

        <div class="field">
            <label for="visitor-service">Service visite</label>
            <select id="visitor-service" wire:model="service_id">
                <option value="">— Choisir un service —</option>
                @foreach ($services as $service)
                    <option value="{{ $service->id }}">{{ $service->name }}</option>
                @endforeach
            </select>
            @error('service_id') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="visitor-reason">Motif <span class="field__hint">(facultatif)</span></label>
            <input id="visitor-reason" type="text" wire:model="reason">
            @error('reason') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="btn btn--secondary btn--block" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="save">Enregistrer le visiteur</span>
            <span wire:loading wire:target="save">Enregistrement…</span>
        </button>
    </form>

    @if ($lastRegistered)
        <div class="ticket ticket--muted" role="status">
            <p class="ticket__label">Fiche visiteur</p>
            <p class="ticket__code">{{ $lastRegistered['visitor_code'] }}</p>
            <p class="ticket__meta">
                {{ $lastRegistered['name'] }} — {{ $lastRegistered['service'] }},
                ticket n° {{ $lastRegistered['token'] }}
            </p>
        </div>
    @endif
</section>
