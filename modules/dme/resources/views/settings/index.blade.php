@extends('dme::layouts.app')

@section('title', 'Paramètres')

@section('content')
    <x-dme::page-header title="Paramètres"
                   subtitle="Configuration effective de l'application. Les valeurs proviennent des fichiers de configuration et des variables d'environnement ; aucun secret n'est affiché."/>

    @include('dme::settings.partials.account')

    <h2 class="mt-6 mb-3 text-sm font-semibold tracking-wide text-ink-500 uppercase">
        Administration
    </h2>

    <div class="grid gap-4 lg:grid-cols-2">

        <section class="k-card">
            <div class="k-card-header"><h2 class="k-card-title">Établissement</h2></div>

            {{-- Quand une application hôte administre ces coordonnées, ce sont
                 les siennes qui font foi partout : interface et documents. Le
                 formulaire du module serait alors sans effet : mieux vaut ne
                 pas le proposer que laisser saisir dans le vide. --}}
            @if (\Keneya\Dme\Dme::facilityIsProvidedByHost())
                <dl class="k-card-body space-y-2.5 text-sm">
                    @foreach ([
                        'Nom' => $facility['name'],
                        'Adresse' => $facility['address'],
                        'Téléphone' => $facility['phone'],
                        'Adresse e-mail' => $facility['email'],
                    ] as $label => $value)
                        <div>
                            <dt class="text-xs text-ink-500">{{ $label }}</dt>
                            <dd class="text-ink-800">{{ $value ?: '-' }}</dd>
                        </div>
                    @endforeach
                    <p class="border-t border-ink-100 pt-3 text-xs text-ink-500">
                        Ces coordonnées sont tenues par l'application qui héberge le
                        dossier médical. Elles s'y modifient, et le changement se
                        voit ici aussitôt.
                    </p>
                </dl>
            @else
            <form action="{{ route('dme.settings.facility.update') }}" method="POST" class="k-card-body space-y-3">
                @csrf
                @method('PUT')
                @foreach ([
                    'name' => ['Nom', $facility['name']],
                    'address' => ['Adresse', $facility['address']],
                    'phone' => ['Téléphone', $facility['phone']],
                    'email' => ['Adresse e-mail', $facility['email']],
                ] as $field => [$label, $value])
                    <div>
                        <label for="facility-{{ $field }}" class="k-label">{{ $label }}</label>
                        <input id="facility-{{ $field }}" name="{{ $field }}"
                               type="{{ $field === 'email' ? 'email' : 'text' }}"
                               value="{{ old($field, $value) }}" class="k-input">
                        @error($field)
                            <p class="k-error">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
                <div class="flex justify-end pt-1">
                    <button type="submit" class="k-btn-primary">
                        <x-dme::icon name="check" class="h-4 w-4"/> Enregistrer
                    </button>
                </div>
            </form>
            @endif
        </section>

        <section class="k-card">
            <div class="k-card-header"><h2 class="k-card-title">Identifiants métier</h2></div>
            <form action="{{ route('dme.settings.identifiers.update') }}" method="POST" class="k-card-body">
                @csrf
                @method('PUT')
                <p class="mb-3 text-sm text-ink-600">
                    Format <span class="font-mono">PRÉFIXE-ANNÉE-SÉQUENCE</span>. Changer un préfixe
                    n'affecte que les identifiants générés ensuite : ceux déjà attribués restent
                    inchangés, et la séquence du nouveau préfixe repart de un.
                </p>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach ($identifiers as $key => $prefix)
                        <div class="rounded-lg border border-ink-200 px-3 py-2">
                            <label for="prefix-{{ $key }}" class="text-xs text-ink-500">
                                {{ str_replace('_', ' ', $key) }}
                            </label>
                            <div class="mt-1 flex items-center gap-1 font-mono text-sm">
                                <input id="prefix-{{ $key }}" name="prefixes[{{ $key }}]"
                                       value="{{ old('prefixes.'.$key, $prefix) }}" maxlength="8"
                                       class="k-input w-20 px-2 py-1 text-center uppercase">
                                <span class="text-ink-400">-{{ now()->format('Y') }}-000001</span>
                            </div>
                        </div>
                    @endforeach
                </div>
                @error('prefixes')
                    <p class="k-error mt-2">{{ $message }}</p>
                @enderror
                <div class="flex justify-end pt-3">
                    <button type="submit" class="k-btn-primary">
                        <x-dme::icon name="check" class="h-4 w-4"/> Enregistrer
                    </button>
                </div>
            </form>
        </section>

        <section class="k-card">
            <div class="k-card-header"><h2 class="k-card-title">Documents</h2></div>
            <dl class="k-card-body space-y-2.5 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-ink-500">Disque de stockage</dt>
                    <dd class="font-mono font-medium text-ink-900">{{ $documents['disk'] }} (privé)</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-ink-500">Taille maximale</dt>
                    <dd class="font-medium text-ink-900">{{ round($documents['max_size_kb'] / 1024) }} Mo</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-ink-500">Formats acceptés</dt>
                    <dd class="text-right font-medium text-ink-900">{{ implode(', ', $documents['allowed_mimes']) }}</dd>
                </div>
                <p class="border-t border-ink-100 pt-2.5 text-xs text-ink-500">
                    Aucun document n'est accessible par une URL de fichier. Tout téléchargement passe par
                    une route contrôlée, vérifie la permission de l'utilisateur et est inscrit au journal d'audit.
                </p>
            </dl>
        </section>

        <section class="k-card">
            <div class="k-card-header"><h2 class="k-card-title">Service SMS</h2></div>
            <dl class="k-card-body space-y-2.5 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-ink-500">Passerelle</dt>
                    <dd class="font-mono font-medium text-ink-900">{{ $smsGateway }}</dd>
                </div>
                @if ($smsSimulated)
                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        Passerelle de simulation : aucun SMS réel n'est émis.
                        Définissez <span class="font-mono">SMS_GATEWAY=smsgate</span> pour un envoi réel.
                    </p>
                @endif
                <div class="flex justify-between gap-4">
                    <dt class="text-ink-500">Suivi d'acheminement</dt>
                    <dd class="font-medium text-ink-900">
                        {{ $smsTracking['enabled'] ? 'Activé' : 'Désactivé' }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-ink-500">Tentatives maximales</dt>
                    <dd class="font-medium text-ink-900">{{ $smsRetry['max_attempts'] }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-ink-500">Délai entre tentatives</dt>
                    <dd class="font-medium text-ink-900">{{ $smsRetry['delay_seconds'] }} s</dd>
                </div>
                <div>
                    <dt class="mb-1 text-ink-500">Modèles actifs</dt>
                    <dd>
                        <ul class="space-y-0.5">
                            @foreach ($templates as $template)
                                <li class="font-mono text-xs text-ink-700">{{ $template->key }}</li>
                            @endforeach
                        </ul>
                    </dd>
                </div>
            </dl>
        </section>

        <section class="k-card lg:col-span-2">
            <div class="k-card-header">
                <h2 class="k-card-title">Rôles et permissions</h2>
                <span class="text-xs text-ink-500">
                    {{ count(\Keneya\Dme\Support\Rbac::allPermissions()) }} permissions · {{ $roles->count() }} rôles
                </span>
            </div>

            <form action="{{ route('dme.settings.roles.update') }}" method="POST" class="k-card-body">
                @csrf
                @method('PUT')

                <p class="mb-3 text-sm text-ink-600">
                    Les permissions sont vérifiées côté serveur par les policies. L'interface masque les
                    actions interdites par confort, mais un accès direct par URL est refusé de la même manière.
                </p>

                @if ($canEditRoles)
                    <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        Une permission retirée prend effet immédiatement, y compris pour les sessions déjà
                        ouvertes. Les permissions d'administration du rôle Administrateur
                        ({{ implode(', ', $lockedPermissions) }}) sont verrouillées : les retirer fermerait
                        cet écran sans autre issue qu'une intervention en base.
                    </div>
                @else
                    <div class="mb-4 rounded-lg border border-ink-200 bg-ink-50 px-3 py-2 text-xs text-ink-600">
                        Lecture seule : la permission <span class="font-mono">roles.manage</span> est requise
                        pour modifier cette matrice.
                    </div>
                @endif

                <div class="overflow-x-auto">
                    <table class="k-table">
                        <caption class="sr-only">Matrice des rôles et permissions</caption>
                        <thead>
                            <tr>
                                <th scope="col">Permission</th>
                                @foreach ($roleLabels as $key => $label)
                                    <th scope="col" class="text-center">{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($permissionGroups as $group => $permissions)
                                <tr>
                                    <th scope="colgroup" colspan="{{ count($roleLabels) + 1 }}"
                                        class="bg-ink-50 px-4 py-2 text-left text-xs font-semibold text-ink-700">
                                        {{ $group }}
                                    </th>
                                </tr>
                                @foreach ($permissions as $permission => $description)
                                    <tr>
                                        <td>
                                            <span class="font-mono text-xs text-ink-700">{{ $permission }}</span>
                                            <span class="block text-xs text-ink-500">{{ $description }}</span>
                                        </td>
                                        @foreach ($roleLabels as $roleKey => $roleLabel)
                                            @php
                                                $granted = in_array($permission, $rolePermissions[$roleKey] ?? [], true);
                                                $locked = $roleKey === \Keneya\Dme\Support\Rbac::ROLE_ADMIN
                                                    && in_array($permission, $lockedPermissions, true);
                                            @endphp
                                            <td class="text-center">
                                                @if ($canEditRoles && ! $locked)
                                                    <input type="checkbox"
                                                           name="permissions[{{ $roleKey }}][]"
                                                           value="{{ $permission }}"
                                                           @checked($granted)
                                                           class="h-4 w-4 rounded border-ink-300 text-clinic-600
                                                                  focus:ring-clinic-500"
                                                           aria-label="{{ $roleLabel }} - {{ $description }}">
                                                @elseif ($locked)
                                                    {{-- Verrouillée : cochée, envoyée, non décochable. --}}
                                                    <input type="hidden"
                                                           name="permissions[{{ $roleKey }}][]"
                                                           value="{{ $permission }}">
                                                    <x-dme::icon name="lock" class="mx-auto h-4 w-4 text-ink-400"/>
                                                    <span class="sr-only">{{ $roleLabel }} : verrouillé</span>
                                                @elseif ($granted)
                                                    <x-dme::icon name="check" class="mx-auto h-4 w-4 text-keneya-600"/>
                                                    <span class="sr-only">{{ $roleLabel }} : autorisé</span>
                                                @else
                                                    <span class="text-ink-300" aria-hidden="true">-</span>
                                                    <span class="sr-only">{{ $roleLabel }} : non autorisé</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($canEditRoles)
                    <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
                        <button type="submit" class="k-btn-primary">
                            <x-dme::icon name="check" class="h-4 w-4"/> Enregistrer la matrice
                        </button>
                    </div>
                @endif
            </form>

            @if ($canEditRoles)
                <div class="border-t border-ink-100 px-4 py-3">
                    <form action="{{ route('dme.settings.roles.reset') }}" method="POST"
                          onsubmit="return confirm('Rétablir la matrice d'origine ? Les modifications en cours seront perdues.');">
                        @csrf
                        <button type="submit" class="k-btn-ghost text-xs">
                            <x-dme::icon name="arrow-left" class="h-3.5 w-3.5"/>
                            Rétablir la configuration d'origine
                        </button>
                    </form>
                </div>
            @endif

            @if ($canEditRoles)
                <div class="border-t border-ink-100 px-4 py-3">
                    <form action="{{ route('dme.settings.roles.store') }}" method="POST"
                          class="flex flex-wrap items-end gap-2">
                        @csrf
                        <div>
                            <label for="new-role-label" class="k-label">Ajouter un rôle</label>
                            <input id="new-role-label" name="label" type="text" required maxlength="60"
                                   placeholder="p. ex. Secrétariat médical" class="k-input">
                            @error('label')
                                <p class="k-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="k-btn-secondary">
                            <x-dme::icon name="plus" class="h-4 w-4"/> Créer
                        </button>
                    </form>
                    <p class="mt-1.5 text-xs text-ink-500">
                        Le rôle est créé sans aucune permission ; accordez-les-lui dans la matrice ci-dessus.
                    </p>
                </div>
            @endif
        </section>

        <section class="k-card lg:col-span-2">
            <div class="k-card-header"><h2 class="k-card-title">Services de l'établissement</h2></div>
            <div class="k-card-body grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($services as $service)
                    <div class="flex items-center justify-between gap-2 rounded-lg border border-ink-200 px-3 py-2">
                        <div>
                            <p class="text-sm font-medium text-ink-900">{{ $service->name }}</p>
                            <p class="font-mono text-xs text-ink-500">{{ $service->code }}</p>
                        </div>
                        <x-dme::status-badge :status="$service->is_active ? 'active' : 'cancelled'"
                                        :label="$service->is_active ? 'Actif' : 'Inactif'"/>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-ink-100 px-4 py-3">
                <form action="{{ route('dme.settings.services.store') }}" method="POST"
                      class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div>
                        <label for="new-service-code" class="k-label">Code</label>
                        <input id="new-service-code" name="code" type="text" required maxlength="10"
                               placeholder="p. ex. ORL" class="k-input w-28 uppercase">
                        @error('code')
                            <p class="k-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="min-w-48 flex-1">
                        <label for="new-service-name" class="k-label">Nom du service</label>
                        <input id="new-service-name" name="name" type="text" required maxlength="100"
                               placeholder="p. ex. Oto-rhino-laryngologie" class="k-input">
                        @error('name')
                            <p class="k-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="new-service-type" class="k-label">Type</label>
                        <select id="new-service-type" name="type" class="k-select">
                            <option value="clinical">Clinique</option>
                            <option value="medico_technical">Médico-technique</option>
                            <option value="administrative">Administratif</option>
                        </select>
                    </div>
                    <button type="submit" class="k-btn-secondary">
                        <x-dme::icon name="plus" class="h-4 w-4"/> Ajouter
                    </button>
                </form>
            </div>
        </section>
    </div>
@endsection
