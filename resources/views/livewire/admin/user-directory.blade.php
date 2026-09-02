{{-- Annuaire des comptes. Lecture seule : la creation d'un agent passe par
     « Personnels », ou le type choisi decide du role et du rattachement. --}}
<div class="pile">

    <x-page-header
        :fil="['Accueil', 'Systeme', 'Utilisateurs']"
        titre="Comptes et acces"
        sous-titre="Qui peut se connecter, sous quel role. La creation d'un agent se fait dans Personnel." />

    <x-card title="Rechercher un compte" icon="recherche">
        <div class="field-row">
            <x-field name="recherche-compte" label="Nom ou adresse e-mail" class="field--wide">
                <input id="recherche-compte" type="search" wire:model.live.debounce.300ms="recherche"
                       placeholder="Amadou, accueil@…">
            </x-field>

            <x-field name="filtre-role" label="Role">
                <select id="filtre-role" wire:model.live="role">
                    <option value="">Tous les roles</option>
                    @foreach ($roles as $cle => $libelle)
                        <option value="{{ $cle }}">{{ $libelle }}</option>
                    @endforeach
                </select>
            </x-field>
        </div>
    </x-card>

    <x-card title="Comptes" icon="utilisateurs">
        @if ($comptes->isEmpty())
            <p class="empty">Aucun compte ne correspond a cette recherche.</p>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Nom</th>
                            <th scope="col">Adresse e-mail</th>
                            <th scope="col">Role</th>
                            <th scope="col">Compte cree le</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($comptes as $compte)
                            <tr wire:key="compte-{{ $compte->id }}">
                                <th scope="row">{{ $compte->name }}</th>
                                <td>{{ $compte->email }}</td>
                                <td><span class="badge badge--neutral">{{ $compte->roleLabel() }}</span></td>
                                <td class="mono">{{ $compte->created_at?->format('d/m/Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{ $comptes->links() }}
        @endif
    </x-card>
</div>
