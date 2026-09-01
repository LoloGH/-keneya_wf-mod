<section class="card">
    <h2 class="card__title">Pathologies</h2>
    <p class="hint">
        Etiquettes posees a la conclusion d'une consultation, toujours
        facultatives. Elles servent a s'adresser plus tard a un groupe de
        patients — elles ne codent pas un diagnostic.
    </p>

    <form wire:submit="save" class="form form--inline-wrap">
        <div class="field">
            <label for="pathologie-nom">Nom</label>
            <input id="pathologie-nom" type="text" wire:model="name" placeholder="Hypertension arterielle">
            @error('name') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="btn-row">
            <button type="submit" class="btn btn--primary">{{ $editingId ? 'Enregistrer' : 'Ajouter' }}</button>
            @if ($editingId)
                <button type="button" class="btn btn--ghost" wire:click="cancel">Annuler</button>
            @endif
        </div>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Pathologie</th><th>Visites</th><th></th></tr></thead>
            <tbody>
                @forelse ($pathologies as $pathologie)
                    <tr>
                        <td>{{ $pathologie->name }}</td>
                        <td class="mono">{{ $pathologie->visits_count }}</td>
                        <td>
                            <div class="btn-row">
                                <button type="button" class="btn btn--ghost" wire:click="edit({{ $pathologie->id }})">Modifier</button>
                                <button type="button" class="btn btn--ghost" wire:click="delete({{ $pathologie->id }})">Supprimer</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">Aucune pathologie enregistree.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
