<section class="card">
    <h2 class="card__title">Medecins</h2>

    <form wire:submit="save" class="form form--inline-wrap">
        <div class="field">
            <label for="doctor-name">Nom</label>
            <input id="doctor-name" type="text" wire:model="name">
            @error('name') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="doctor-email">Adresse e-mail</label>
            <input id="doctor-email" type="email" wire:model="email">
            @error('email') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="doctor-password">
                Mot de passe
                @if ($editingId) <span class="field__hint">(laisser vide pour conserver)</span> @endif
            </label>
            <input id="doctor-password" type="password" wire:model="password" autocomplete="new-password">
            @error('password') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="doctor-phone">Telephone <span class="field__hint">(pour les resultats par SMS)</span></label>
            <input id="doctor-phone" type="tel" inputmode="tel" wire:model="phone">
            @error('phone') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="doctor-service">Service</label>
            <select id="doctor-service" wire:model="service_id">
                <option value="">— Choisir un service —</option>
                @foreach ($services as $service)
                    <option value="{{ $service->id }}">{{ $service->name }}</option>
                @endforeach
            </select>
            @error('service_id') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="btn-row">
            <button type="submit" class="btn btn--primary">
                {{ $editingId ? 'Enregistrer' : 'Ajouter le medecin' }}
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
                    <th>Nom</th>
                    <th>E-mail</th>
                    <th>Telephone</th>
                    <th>Service</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($doctors as $doctor)
                    <tr>
                        <td>{{ $doctor->user->name }}</td>
                        <td>{{ $doctor->user->email }}</td>
                        <td>{{ $doctor->phone ?: '—' }}</td>
                        <td>{{ $doctor->service->name }}</td>
                        <td>
                            <div class="btn-row">
                                <button type="button" class="btn btn--ghost" wire:click="edit({{ $doctor->id }})">
                                    Modifier / reaffecter
                                </button>
                                <x-delete-action :click="'delete('.$doctor->id.')'"
                                                 label="Supprimer ce medecin"
                                                 confirm="Supprimer ce medecin ? Le compte part avec son dernier rattachement." />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Aucun medecin.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
