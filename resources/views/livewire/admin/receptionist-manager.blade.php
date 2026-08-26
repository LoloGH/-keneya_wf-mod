<section class="card">
    <h2 class="card__title">Receptionnistes</h2>

    <form wire:submit="save" class="form form--inline-wrap">
        <div class="field">
            <label for="receptionist-name">Nom</label>
            <input id="receptionist-name" type="text" wire:model="name">
            @error('name') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="receptionist-email">Adresse e-mail</label>
            <input id="receptionist-email" type="email" wire:model="email">
            @error('email') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="receptionist-password">
                Mot de passe
                @if ($editingId) <span class="field__hint">(laisser vide pour conserver)</span> @endif
            </label>
            <input id="receptionist-password" type="password" wire:model="password" autocomplete="new-password">
            @error('password') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="btn-row">
            <button type="submit" class="btn btn--primary">
                {{ $editingId ? 'Enregistrer' : 'Ajouter la receptionniste' }}
            </button>
            @if ($editingId)
                <button type="button" class="btn btn--ghost" wire:click="cancel">Annuler</button>
            @endif
        </div>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Nom</th><th>E-mail</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($receptionists as $receptionist)
                    <tr>
                        <td>{{ $receptionist->user->name }}</td>
                        <td>{{ $receptionist->user->email }}</td>
                        <td>
                            <div class="btn-row">
                                <button type="button" class="btn btn--ghost" wire:click="edit({{ $receptionist->id }})">
                                    Modifier
                                </button>
                                <x-delete-action :click="'delete('.$receptionist->id.')'"
                                                 label="Supprimer cette receptionniste"
                                                 confirm="Supprimer cette receptionniste et son compte ?" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">Aucune receptionniste.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
