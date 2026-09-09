<x-card title="Types de personnel" icon="personnel">
    <p class="hint">
        Un type <strong>adosse a un role</strong> reutilise telle quelle une des quatre
        interfaces existantes. Un type <strong>sans role</strong> recoit sa propre interface
        sur <code>/staff/&lt;slug&gt;</code>, composee des seules fonctions cochees ci-dessous.
    </p>

    <form wire:submit="save" class="form">
        <div class="form form--inline-wrap">
            <div class="field">
                <label for="staff-type-name">Nom du type</label>
                <input id="staff-type-name" type="text" wire:model="name" placeholder="Infirmier, Sage-femme...">
                @error('name') <p class="field__error">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="staff-type-role">Interface</label>
                <select id="staff-type-role" wire:model.live="matched_role">
                    <option value="">Interface dediee (/staff/...)</option>
                    @foreach ($this->reusableRoles() as $role => $label)
                        <option value="{{ $role }}">Reutiliser l'interface « {{ $label }} »</option>
                    @endforeach
                </select>
                @error('matched_role') <p class="field__error">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Les cases sont proposees pour TOUS les types depuis le v3.2.2 :
             un role code impose seulement les siennes, il n'interdit plus de
             moduler le reste. --}}
        @php
            $obligatoires = $this->requiredCapabilities();
            $optionnelles = $this->optionalCapabilities();
        @endphp

        <fieldset class="weekdays">
            <legend>Fonctions de ce type</legend>
            <div class="capabilities">
                @foreach ($allCapabilities as $capability => $meta)
                    @php
                        $impose = in_array($capability, $obligatoires, true);
                        $offert = in_array($capability, $optionnelles, true);
                    @endphp

                    @if ($impose || $offert)
                        <label class="weekdays__day @if ($impose) weekdays__day--locked @endif"
                               @if ($impose) title="Indispensable au fonctionnement de ce role" @endif>
                            {{-- Une case obligatoire est cochee et verrouillee.
                                 Le refus ne tient pas a cet attribut : le
                                 modele reintroduit la capacite a
                                 l'enregistrement, quoi qu'envoie le client. --}}
                            <input type="checkbox" value="{{ $capability }}"
                                   @if ($impose) checked disabled aria-describedby="cap-lock-{{ $capability }}"
                                   @else wire:model.live="capabilities" @endif>
                            <span>{{ $meta['label'] }}</span>
                            @if ($impose)
                                <span class="capability__lock" id="cap-lock-{{ $capability }}">obligatoire</span>
                            @endif
                        </label>
                    @endif
                @endforeach
            </div>
            @error('capabilities') <p class="field__error">{{ $message }}</p> @enderror
        </fieldset>

        @if ($matched_role !== '')
            <p class="hint">
                Ce type reutilise l'interface du role choisi. Les fonctions marquees
                « obligatoire » ne peuvent pas etre retirees.
            </p>
        @endif

        {{-- L'admin doit voir ce qu'il vient de creer avant qu'un membre du
             personnel ne s'y connecte. --}}
        <div class="preview">
            <h3 class="card__subtitle">Ce que cette personne verra</h3>
            <ul class="preview__list">
                @foreach ($this->previewSections() as $section)
                    <li>{{ $section }}</li>
                @endforeach
            </ul>
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
                <tr><th>Type</th><th>Interface</th><th>Fonctions</th><th>Personnes</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($types as $type)
                    <tr>
                        <td>{{ $type->name }}</td>
                        <td>
                            @if ($type->usesFixedRole())
                                {{ \App\Support\Roles::label($type->matched_role) }}
                            @else
                                <code>/staff/{{ $type->slug }}</code>
                            @endif
                        </td>
                        <td>
                            {{ count($type->enabledOptionalCapabilities()) }}
                            <span class="hint">/ {{ count($type->optionalCapabilities()) }}</span>
                        </td>
                        <td>{{ $type->members_count }}</td>
                        <td>
                            <div class="btn-row">
                                <button type="button" class="btn btn--ghost"
                                        wire:click="edit({{ $type->id }})">Modifier</button>
                                <x-delete-action :click="'delete('.$type->id.')'"
                                                 label="Supprimer ce type de personnel"
                                                 confirm="Supprimer ce type de personnel ?" />
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-card>
