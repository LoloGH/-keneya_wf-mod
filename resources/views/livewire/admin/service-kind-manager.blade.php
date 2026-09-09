<x-card title="Types de service" icon="services">
    <p class="hint">
        Un type coche « paiement prealable » impose un passage par la
        <strong>{{ \App\Models\Service::CAISSE_SERVICES }}</strong> avant qu'un patient
        renvoye vers un service de ce type y soit pris en charge.
    </p>

    <form wire:submit="save" class="form form--inline-wrap">
        <div class="field">
            <label for="kind-name">Nom du type</label>
            <input id="kind-name" type="text" wire:model="name" placeholder="Imagerie, Pharmacie...">
            @error('name') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field field--check">
            <label for="kind-gate">
                <input id="kind-gate" type="checkbox" wire:model="requires_payment_gate">
                Paiement prealable a la prise en charge
            </label>
            @error('requires_payment_gate') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="btn-row">
            <button type="submit" class="btn btn--primary">
                {{ $editingId ? 'Enregistrer' : 'Ajouter le type' }}
            </button>
            @if ($editingId)
                <button type="button" class="btn btn--ghost" wire:click="cancel">Annuler</button>
            @endif
        </div>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Type</th><th>Paiement prealable</th><th>Services</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($kinds as $kind)
                    <tr>
                        <td>
                            {{ $kind->name }}
                            @if ($kind->isBuiltIn())
                                <span class="badge badge--neutral">d'origine</span>
                            @endif
                        </td>
                        <td>{{ $kind->requires_payment_gate ? 'Oui' : 'Non' }}</td>
                        <td>{{ $kind->services_count }}</td>
                        <td>
                            <div class="btn-row">
                                <button type="button" class="btn btn--ghost"
                                        wire:click="edit({{ $kind->id }})">Modifier</button>
                                {{-- L'action reste offerte meme pour un type
                                     d'origine ou encore utilise : c'est le
                                     serveur qui refuse, en disant pourquoi. --}}
                                <x-delete-action :click="'delete('.$kind->id.')'"
                                                 label="Supprimer ce type de service"
                                                 confirm="Supprimer ce type de service ?" />
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-card>
