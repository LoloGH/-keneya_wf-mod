<x-card title="Plannings du personnel" icon="planning">
    <p class="hint">Chaque agent consulte son planning en lecture seule dans son interface.</p>

    <form wire:submit="save" class="form form--inline-wrap">
        <div class="field">
            <label for="schedule-user">Membre du personnel</label>
            <select id="schedule-user" wire:model="user_id">
                <option value="">— Choisir —</option>
                @foreach ($staff as $member)
                    <option value="{{ $member->id }}">
                        {{ $member->name }} ({{ $member->roleLabel() }})
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

    {{-- Suppression groupee : une generation produit des dizaines de creneaux,
         les retirer un par un n'est pas praticable. La barre n'apparait que
         lorsqu'une case est cochee — elle ne pese pas sur l'ecran le reste du
         temps. --}}
    @if (count($selected) > 0)
        <div class="bulkbar" role="status">
            <span>{{ count($selected) }} creneau(x) selectionne(s).</span>
            <button type="button" class="btn btn--ghost" wire:click="deleteSelected"
                    wire:confirm="Supprimer les {{ count($selected) }} creneaux selectionnes ?">
                Supprimer la selection
            </button>
            <button type="button" class="btn btn--ghost" wire:click="$set('selected', [])">
                Tout decocher
            </button>
        </div>
    @endif

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th class="table__check">
                        <input type="checkbox" wire:click="toggleAll"
                               @checked(count($selected) > 0 && count($selected) === $schedules->count())
                               aria-label="Tout selectionner">
                    </th>
                    <th>Personne</th><th>Date</th><th>Horaire</th><th>Service</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($schedules as $schedule)
                    <tr wire:key="creneau-{{ $schedule->id }}">
                        <td class="table__check">
                            <input type="checkbox" value="{{ $schedule->id }}" wire:model.live="selected"
                                   aria-label="Selectionner ce creneau">
                        </td>
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
                    <tr><td colspan="6" class="empty">Aucun creneau enregistre.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>
