<x-card title="Tarifs" icon="tarif">
    <p class="hint">
        Les actes facturables et leur prix, en FCFA. Le medecin choisit l'acte
        precis au moment du renvoi ; le caissier n'a plus qu'a confirmer le
        montant, et le patient repart avec un recu qui nomme ce qu'il a paye.
    </p>

    <form wire:submit="save" class="form form--inline-wrap">
        <div class="field">
            <label for="tarif-name">Nom de l'acte</label>
            <input id="tarif-name" type="text" wire:model="name"
                   placeholder="Echographie abdominale">
            @error('name') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="tarif-service">Service <span class="field__hint">(facultatif)</span></label>
            <select id="tarif-service" wire:model="service_id">
                <option value="">— Tarif generique —</option>
                @foreach ($services as $service)
                    <option value="{{ $service->id }}">{{ $service->name }}</option>
                @endforeach
            </select>
            @error('service_id') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="tarif-price">Tarif <span class="field__hint">en FCFA</span></label>
            <input id="tarif-price" type="number" inputmode="numeric" min="0" step="1" wire:model="price">
            @error('price') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="btn-row">
            <button type="submit" class="btn btn--primary">
                {{ $editingId ? 'Enregistrer' : 'Ajouter' }}
            </button>
            @if ($editingId)
                <button type="button" class="btn btn--ghost" wire:click="cancel">Annuler</button>
            @endif
        </div>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Acte</th><th>Service</th><th>Tarif</th><th>Utilisations</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td>
                            {{ $item->name }}
                            @if ($ticketItemId === $item->id)
                                <span class="badge badge--neutral">ticket</span>
                            @endif
                        </td>
                        <td>{{ $item->service?->name ?? 'Tous services' }}</td>
                        <td class="mono">{{ $item->formattedPrice() }}</td>
                        <td class="mono">{{ $item->payments_count }} encaisse(s), {{ $item->referrals_count }} renvoi(s)</td>
                        <td>
                            <div class="btn-row">
                                <button type="button" class="btn btn--ghost" wire:click="edit({{ $item->id }})">Modifier</button>
                                <button type="button" class="btn btn--ghost" wire:click="delete({{ $item->id }})">Supprimer</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Aucun tarif enregistre.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h3 class="card__subtitle">Ticket de consultation</h3>
    <p class="hint">
        Le tarif applique automatiquement a l'enregistrement, a la caisse ticket.
        Tant qu'aucun n'est choisi, le caissier saisit le montant lui-meme.
    </p>

    <form wire:submit="saveTicketItem" class="form form--inline-wrap">
        <div class="field">
            <label for="tarif-ticket">Tarif du ticket</label>
            <select id="tarif-ticket" wire:model="ticketItemId">
                <option value="">— Aucun —</option>
                @foreach ($ticketChoices as $choix)
                    <option value="{{ $choix->id }}">{{ $choix->label() }}</option>
                @endforeach
            </select>
            @error('ticketItemId') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="btn btn--secondary">Enregistrer le ticket</button>
    </form>
</x-card>
