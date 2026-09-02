<x-card title="Journal d'audit" icon="audit">
    <p class="hint">Lecture seule. Aucune ligne ne peut etre modifiee ni supprimee.</p>

    <div class="form form--inline-wrap">
        <div class="field">
            <label for="audit-causer">Utilisateur</label>
            <select id="audit-causer" wire:model.live="causerId">
                <option value="">Tous</option>
                @foreach ($causers as $causer)
                    <option value="{{ $causer->id }}">{{ $causer->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="audit-event">Type d'action</label>
            <select id="audit-event" wire:model.live="event">
                <option value="">Toutes</option>
                @foreach ($events as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="audit-from">Du</label>
            <input id="audit-from" type="date" wire:model.live="from">
        </div>

        <div class="field">
            <label for="audit-to">Au</label>
            <input id="audit-to" type="date" wire:model.live="to">
        </div>

        <button type="button" class="btn btn--ghost" wire:click="resetFilters">Reinitialiser</button>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Detail</th></tr>
            </thead>
            <tbody>
                @forelse ($activities as $activity)
                    <tr>
                        <td class="mono">{{ $activity->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $activity->causer?->name ?? 'Systeme' }}</td>
                        <td>{{ \App\Support\Audit::label($activity->event) }}</td>
                        <td>{{ $activity->description }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">Aucune trace ne correspond a ces filtres.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $activities->links() }}
</x-card>
