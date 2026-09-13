{{-- Mon compte : informations et mot de passe. Partagé par les deux
     variantes de l'écran Paramètres : l'administrateur a lui aussi un
     compte à gérer. --}}
<div class="grid gap-4 lg:grid-cols-2">

    <section class="k-card self-start">
        <div class="k-card-header">
            <h2 class="k-card-title">Mes informations</h2>
            <span class="text-xs text-ink-500">Lecture seule</span>
        </div>
        <div class="k-card-body">
            <dl class="divide-y divide-ink-100 text-sm">
                @foreach ([
                    'Nom' => $user->displayName(),
                    'Adresse e-mail' => $user->email,
                    'Matricule' => $user->matricule ?: '-',
                    'Téléphone' => $user->phone ?: '-',
                    'Service' => $user->service?->name ?: '-',
                    'Spécialité' => $user->speciality ?: '-',
                    'Rôle' => $roleLabels[$user->getRoleNames()->first()] ?? '-',
                ] as $label => $value)
                    <div class="flex items-start justify-between gap-4 py-2">
                        <dt class="text-ink-500">{{ $label }}</dt>
                        <dd class="text-right font-medium text-ink-900">{{ $value }}</dd>
                    </div>
                @endforeach
                <div class="flex items-start justify-between gap-4 py-2">
                    <dt class="text-ink-500">Dernière connexion</dt>
                    <dd class="text-right font-medium text-ink-900">
                        {{ $user->last_login_at?->translatedFormat('d M Y · H:i') ?? '-' }}
                    </dd>
                </div>
            </dl>

            <p class="k-hint mt-3">
                Ces informations sont tenues par l'administration. Signalez-lui toute erreur -
                elles signent vos actes dans les dossiers patients.
            </p>
        </div>
    </section>

    <div class="space-y-4">
        <section class="k-card">
            <div class="k-card-header"><h2 class="k-card-title">Changer mon mot de passe</h2></div>
            <form action="{{ route('dme.settings.password.update') }}" method="POST" class="k-card-body space-y-3">
                @csrf
                @method('PUT')

                <div>
                    <label for="current_password" class="k-label">Mot de passe actuel</label>
                    <input id="current_password" name="current_password" type="password" required
                           autocomplete="current-password"
                           class="k-input @error('current_password') border-red-500 @enderror">
                    <x-dme::field-error name="current_password"/>
                </div>

                <div>
                    <label for="password" class="k-label">Nouveau mot de passe</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password"
                           class="k-input @error('password') border-red-500 @enderror">
                    <x-dme::field-error name="password"/>
                </div>

                <div>
                    <label for="password_confirmation" class="k-label">Confirmer le nouveau mot de passe</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required
                           autocomplete="new-password" class="k-input">
                </div>

                <p class="k-hint">
                    Les autres sessions ouvertes avec l'ancien mot de passe restent actives jusqu'à leur
                    expiration ; prévenez l'administration si vous soupçonnez un accès non autorisé.
                </p>

                <button type="submit" class="k-btn-primary w-full">
                    <x-dme::icon name="lock" class="h-4 w-4"/> Mettre à jour
                </button>
            </form>
        </section>

        @can('care_orders.view')
            <section class="k-card">
                <div class="k-card-header"><h2 class="k-card-title">Ma garde</h2></div>
                <div class="k-card-body">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-ink-900">
                                {{ $user->is_on_duty ? 'Vous êtes de garde' : 'Vous n\'êtes pas de garde' }}
                            </p>
                            <p class="mt-0.5 text-xs text-ink-500">
                                @if ($user->is_on_duty && $user->on_duty_since)
                                    Depuis le {{ $user->on_duty_since->translatedFormat('d M Y · H:i') }}
                                @else
                                    Les soins programmés laissés ouverts ne vous sont pas listés.
                                @endif
                            </p>
                        </div>
                        <x-dme::status-badge :status="$user->is_on_duty ? 'active' : 'inactive'"
                                        :label="$user->is_on_duty ? 'De garde' : 'Hors garde'"/>
                    </div>

                    <p class="k-hint mt-3">
                        Un soin programmé sans soignant nommé est visible par le personnel de garde du
                        service prescripteur. Votre service : <strong>{{ $user->service?->name ?? 'non renseigné' }}</strong>.
                        @if ($user->service_id === null)
                            Sans service d'affectation, aucun soin ouvert ne peut vous être présenté.
                        @endif
                    </p>

                    <form action="{{ route('dme.settings.duty.toggle') }}" method="POST" class="mt-3">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                                class="{{ $user->is_on_duty ? 'k-btn-secondary' : 'k-btn-primary' }} w-full">
                            <x-dme::icon name="bolt" class="h-4 w-4"/>
                            {{ $user->is_on_duty ? 'Quitter la garde' : 'Prendre la garde' }}
                        </button>
                    </form>
                </div>
            </section>
        @endcan
    </div>
</div>
