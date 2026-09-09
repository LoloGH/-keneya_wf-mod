<x-card class="card--accent" icon="planning"
        title="Generer un planning sur plusieurs jours"
        accroche="Decrivez une plage de dates et les jours concernes : un creneau est cree pour chaque date correspondante. Le formulaire jour par jour ci-dessous reste disponible pour les ajustements ponctuels.">

    <form wire:submit="generate" class="form">
        <div class="form form--inline-wrap">
            <div class="field">
                <label for="bulk-user">Membre du personnel</label>
                <select id="bulk-user" wire:model="user_id">
                    <option value="">Choisir</option>
                    @foreach ($staff as $membre)
                        <option value="{{ $membre->id }}">
                            {{ $membre->name }} ({{ $membre->roleLabel() }})
                        </option>
                    @endforeach
                </select>
                @error('user_id') <p class="field__error">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="bulk-from">Du</label>
                <input id="bulk-from" type="date" wire:model="from">
                @error('from') <p class="field__error">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="bulk-to">Au</label>
                <input id="bulk-to" type="date" wire:model="to">
                @error('to') <p class="field__error">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="bulk-start">Debut</label>
                <input id="bulk-start" type="time" wire:model="start_time">
                @error('start_time') <p class="field__error">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="bulk-end">Fin</label>
                <input id="bulk-end" type="time" wire:model="end_time">
                @error('end_time') <p class="field__error">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="bulk-service">Service <span class="field__hint">(facultatif)</span></label>
                <select id="bulk-service" wire:model="service_id">
                    <option value="">Aucun</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}">{{ $service->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <fieldset class="weekdays">
            <legend>Jours de la semaine</legend>
            <div class="weekdays__row">
                @foreach ($weekdayLabels as $iso => $label)
                    <label class="weekdays__day">
                        <input type="checkbox" value="{{ $iso }}" wire:model="weekdays">
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            @error('weekdays') <p class="field__error">{{ $message }}</p> @enderror
        </fieldset>

        <button type="submit" class="btn btn--primary" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="generate">Generer les creneaux</span>
            <span wire:loading wire:target="generate">Generation...</span>
        </button>
    </form>
</x-card>
