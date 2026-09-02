<x-card title="Personnels" icon="personnel">

    <form wire:submit="save" class="form form--inline-wrap">
        <div class="field">
            <label for="staff-name">Nom</label>
            <input id="staff-name" type="text" wire:model="name">
            @error('name') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="staff-email">Adresse e-mail</label>
            <input id="staff-email" type="email" wire:model="email">
            @error('email') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="staff-password">
                Mot de passe
                @if ($editingRef) <span class="field__hint">(laisser vide pour conserver)</span> @endif
            </label>
            <input id="staff-password" type="password" wire:model="password" autocomplete="new-password">
            @error('password') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        {{-- Le type commande le reste du formulaire : il decide du role, donc
             des champs qui ont un sens. D'ou le .live. --}}
        <div class="field">
            <label for="staff-type">Type de personnel</label>
            <select id="staff-type" wire:model.live="staff_type_id">
                <option value="">— Choisir un type —</option>
                @foreach ($staffTypes as $type)
                    {{-- Le role n'est rappele que s'il n'est pas deja le nom du
                         type : « Caissier — Caissier » n'apprend rien. --}}
                    @php $role = \App\Support\Roles::label($type->matched_role); @endphp
                    @php
                        $suffixe = $type->matched_role
                            ? ($role === $type->name ? '' : ' — '.$role)
                            : ' — interface dediee';
                    @endphp
                    <option value="{{ $type->id }}">
                        {{ $type->name }}{{ $suffixe }}
                        ({{ count($type->enabledOptionalCapabilities()) }} fonction(s) optionnelle(s))
                    </option>
                @endforeach
            </select>
            @error('staff_type_id') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        @if ($this->needsPhone())
            <div class="field">
                <label for="staff-phone">Telephone <span class="field__hint">(pour les resultats par SMS)</span></label>
                <input id="staff-phone" type="tel" inputmode="tel" wire:model="phone">
                @error('phone') <p class="field__error">{{ $message }}</p> @enderror
            </div>
        @endif

        @if ($this->needsService())
            <div class="field">
                <label for="staff-service">Service</label>
                <select id="staff-service" wire:model="service_id">
                    <option value="">— Choisir un service —</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}">{{ $service->name }} ({{ $service->kindLabel() }})</option>
                    @endforeach
                </select>
                @error('service_id') <p class="field__error">{{ $message }}</p> @enderror
            </div>
        @endif

        <div class="btn-row">
            <button type="submit" class="btn btn--primary">
                {{ $editingRef ? 'Enregistrer' : 'Ajouter au personnel' }}
            </button>
            @if ($editingRef)
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
                    <th>Type</th>
                    <th>Service</th>
                    <th>Telephone</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($personnels as $ligne)
                    <tr wire:key="personnel-{{ $ligne['ref'] }}">
                        <td>{{ $ligne['user']->name }}</td>
                        <td>{{ $ligne['user']->email }}</td>
                        <td>
                            {{ $ligne['type'] ?: '—' }}
                            @if ($ligne['alerte'])
                                {{-- Le compte est valide, mais il ne montrera
                                     rien : l'admin doit l'apprendre ici, pas
                                     par un agent qui signale un ecran vide. --}}
                                <p class="field__error">{{ $ligne['alerte'] }}</p>
                            @endif
                        </td>
                        <td>{{ $ligne['service'] ?: '—' }}</td>
                        <td>{{ $ligne['phone'] ?: '—' }}</td>
                        <td>
                            <div class="btn-row">
                                <button type="button" class="btn btn--ghost"
                                        wire:click="edit('{{ $ligne['ref'] }}')">
                                    Modifier / reaffecter
                                </button>
                                <x-delete-action :click="'delete(\''.$ligne['ref'].'\')'"
                                                 label="Supprimer ce membre du personnel"
                                                 confirm="Supprimer ce membre du personnel ? Le compte part avec son dernier rattachement." />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Aucun personnel.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>
