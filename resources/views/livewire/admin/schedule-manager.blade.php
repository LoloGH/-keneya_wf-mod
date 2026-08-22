<section class="card">
    <h2 class="card__title">Plannings du personnel</h2>
    <p class="hint">
        Les creneaux sont crees ici uniquement. Chaque medecin et chaque receptionniste
        consulte le sien, en lecture seule, dans sa propre interface.
    </p>

    <form wire:submit="save" class="form form--inline-wrap">
        <div class="field">
            <label for="schedule-user">Membre du personnel</label>
            <select id="schedule-user" wire:model="user_id">
                <option value="">— Choisir —</option>
                @foreach ($staff as $member)
                    <option value="{{ $member->id }}">
                        {{ $member->name }} ({{ \App\Support\Roles::label($member->scopedRole()) }})
                    </option>
                @endforeach
            </select>
            @error('user_id') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="schedule-date">Date</label>
            <input id="schedule-date" type="date" wire:model="date">
            @error('date') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="schedule-start">Debut</label>
            <input id="schedule-start" type="time" wire:model="start_time">
            @error('start_time') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="schedule-end">Fin</label>
            <input id="schedule-end" type="time" wire:model="end_time">
            @error('end_time') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="schedule-service">Service <span class="field__hint">(facultatif)</span></label>
            <select id="schedule-service" wire:model="service_id">
                <option value="">— Aucun —</option>
                @foreach ($services as $service)
                    <option value="{{ $service->id }}">{{ $service->name }}</option>
                @endforeach
            </select>
            @error('service_id') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="btn-row">
            <button type="submit" class="btn btn--primary">
                {{ $editingId ? 'Enregistrer' : 'Ajouter le creneau' }}
            </button>
            @if ($editingId)
                <button type="button" class="btn btn--ghost" wire:click="cancel">Annuler</button>
            @endif
        </div>
    </form>

    <div class="field">
        <label for="schedule-filter">Filtrer par personne</label>
        <select id="schedule-filter" wire:model.live="filterUserId">
            <option value="">Tout le personnel</option>
            @foreach ($staff as $member)
                <option value="{{ $member->id }}">{{ $member->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Personne</th><th>Date</th><th>Horaire</th><th>Service</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($schedules as $schedule)
                    <tr>
                        <td>{{ $schedule->user->name }}</td>
                        <td>{{ $schedule->date->translatedFormat('D d/m/Y') }}</td>
                        <td class="mono">{{ $schedule->range() }}</td>
                        <td>{{ $schedule->service?->name ?? '—' }}</td>
                        <td>
                            <div class="btn-row">
                                <button type="button" class="btn btn--ghost" wire:click="edit({{ $schedule->id }})">
                                    Modifier
                                </button>
                                <button type="button" class="btn btn--ghost" wire:click="delete({{ $schedule->id }})"
                                        wire:confirm="Supprimer ce creneau ?">
                                    Supprimer
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Aucun creneau enregistre.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
