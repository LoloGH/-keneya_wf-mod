<x-card title="Services" icon="services">

    <form wire:submit="save" class="form form--inline-wrap">
        <div class="field">
            <label for="service-name">Nom du service</label>
            <input id="service-name" type="text" wire:model="name">
            @error('name') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="service-kind">Type</label>
            <select id="service-kind" wire:model.live="service_kind_id">
                <option value="">Choisir un type</option>
                @foreach ($kinds as $kind)
                    <option value="{{ $kind->id }}">
                        {{ $kind->name }}@if ($kind->requires_payment_gate) : paiement prealable @endif
                    </option>
                @endforeach
            </select>
            @error('service_kind_id') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        {{-- Ce que realise un plateau technique (v3.3.1). C'est ce champ qui
             fait apparaitre le bon formulaire de demande quand un medecin y
             envoie un patient : un nom de service ne se devine pas. Il ne
             s'affiche que pour un plateau technique : ailleurs il n'a pas de
             sens, et la valeur est effacee a l'enregistrement. --}}
        @if ($this->estPlateauTechnique())
            <div class="field">
                <label for="exam-kind">Examens realises</label>
                <select id="exam-kind" wire:model="exam_kind">
                    <option value="">Aucun formulaire de demande</option>
                    @foreach (\App\Models\Service::EXAM_KINDS as $cle => $libelle)
                        <option value="{{ $cle }}">{{ $libelle }}</option>
                    @endforeach
                </select>
                <p class="hint">
                    Le medecin qui envoie un patient ici remplira la demande
                    correspondante, et elle partira avec lui.
                </p>
                @error('exam_kind') <p class="field__error">{{ $message }}</p> @enderror
            </div>
        @endif

        <div class="btn-row">
            <button type="submit" class="btn btn--primary">
                {{ $editingId ? 'Enregistrer' : 'Ajouter le service' }}
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
                    <th>Service</th>
                    <th>Type</th>
                    <th>Examens</th>
                    <th>Medecins</th>
                    <th>Passages</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($services as $service)
                    <tr>
                        <td>{{ $service->name }}</td>
                        <td>{{ $service->kindLabel() }}</td>
                        <td>{{ $service->examKindLabel() ?? '-' }}</td>
                        <td class="mono">{{ $service->doctors_count }}</td>
                        <td class="mono">{{ $service->visits_count }}</td>
                        <td>
                            <div class="btn-row">
                                <button type="button" class="btn btn--ghost" wire:click="edit({{ $service->id }})">
                                    Modifier
                                </button>
                                <x-delete-action :click="'delete('.$service->id.')'"
                                                 label="Supprimer ce service"
                                                 :confirm="'Supprimer le service « '.$service->name.' » ?'" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Aucun service.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>
