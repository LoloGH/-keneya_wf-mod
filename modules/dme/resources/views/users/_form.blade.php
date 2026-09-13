@php
    $user = $user ?? null;
    $upcomingDutyPeriods = $upcomingDutyPeriods ?? collect();
    $activeDutyPeriods = $activeDutyPeriods ?? collect();
@endphp

<div class="grid gap-4 lg:grid-cols-2">
    <fieldset class="k-fieldset">
        <legend class="k-fieldset-legend">
            <x-dme::icon name="users" class="h-4.5 w-4.5 text-clinic-600"/> Identité professionnelle
        </legend>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="first_name" class="k-label">Prénom <span class="text-red-600" aria-hidden="true">*</span></label>
                <input id="first_name" name="first_name" type="text" required maxlength="100"
                       value="{{ old('first_name', $user?->first_name) }}" class="k-input">
                <x-dme::field-error name="first_name"/>
            </div>
            <div>
                <label for="last_name" class="k-label">Nom <span class="text-red-600" aria-hidden="true">*</span></label>
                <input id="last_name" name="last_name" type="text" required maxlength="100"
                       value="{{ old('last_name', $user?->last_name) }}" class="k-input">
                <x-dme::field-error name="last_name"/>
            </div>
            <div>
                <label for="title" class="k-label">Titre</label>
                <input id="title" name="title" type="text" maxlength="20"
                       value="{{ old('title', $user?->title) }}" class="k-input" placeholder="Dr, Pr, M., Mme">
            </div>
            <div>
                <label for="matricule" class="k-label">Matricule</label>
                <input id="matricule" name="matricule" type="text" maxlength="50"
                       value="{{ old('matricule', $user?->matricule) }}" class="k-input">
                <x-dme::field-error name="matricule"/>
            </div>
            <div class="sm:col-span-2">
                <label for="speciality" class="k-label">Spécialité / fonction</label>
                <input id="speciality" name="speciality" type="text" maxlength="100"
                       value="{{ old('speciality', $user?->speciality) }}" class="k-input">
            </div>
        </div>
    </fieldset>

    <fieldset class="k-fieldset">
        <legend class="k-fieldset-legend">
            <x-dme::icon name="chat" class="h-4.5 w-4.5 text-clinic-600"/> Contact et affectation
        </legend>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="email" class="k-label">Adresse e-mail <span class="text-red-600" aria-hidden="true">*</span></label>
                <input id="email" name="email" type="email" required maxlength="150"
                       value="{{ old('email', $user?->email) }}" class="k-input" autocomplete="username">
                <x-dme::field-error name="email"/>
            </div>
            <div>
                <label for="phone" class="k-label">Téléphone</label>
                <input id="phone" name="phone" type="tel" maxlength="30"
                       value="{{ old('phone', $user?->phone) }}" class="k-input">
            </div>
            <div>
                <label for="service_id" class="k-label">Service</label>
                <select id="service_id" name="service_id" class="k-select">
                    <option value="">Non affecté</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}"
                            @selected((string) old('service_id', $user?->service_id) === (string) $service->id)>
                            {{ $service->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </fieldset>

    <fieldset class="k-fieldset">
        <legend class="k-fieldset-legend">
            <x-dme::icon name="shield" class="h-4.5 w-4.5 text-clinic-600"/> Rôle et accès
        </legend>
        <div>
            <label for="role" class="k-label">Rôle <span class="text-red-600" aria-hidden="true">*</span></label>
            <select id="role" name="role" required class="k-select">
                @foreach ($roleLabels as $value => $label)
                    <option value="{{ $value }}"
                        @selected(old('role', $user?->roles->first()?->name) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <x-dme::field-error name="role"/>
            <p class="k-hint">
                Le rôle détermine les permissions. Le détail de chaque rôle est consultable
                dans <a href="{{ route('dme.settings.index') }}" class="text-clinic-700 underline">Paramètres</a>.
            </p>
        </div>

        @if ($user)
            <label class="flex items-start gap-2 text-sm text-ink-700">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))
                       class="mt-0.5 h-4 w-4 rounded border-ink-300 text-clinic-600">
                <span>
                    Compte actif
                    <span class="block text-xs text-ink-500">
                        Un compte désactivé ne peut plus se connecter, mais conserve l'intégralité de son
                        historique et de ses signatures d'actes. Les comptes ne sont jamais supprimés.
                    </span>
                </span>
            </label>
            <x-dme::field-error name="is_active"/>
        @endif
    </fieldset>

    <fieldset class="k-fieldset">
        <legend class="k-fieldset-legend">Mot de passe</legend>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="password" class="k-label">
                    Mot de passe
                    @unless ($user) <span class="text-red-600" aria-hidden="true">*</span> @endunless
                </label>
                <input id="password" name="password" type="password" @required(! $user) class="k-input"
                       autocomplete="new-password">
                <x-dme::field-error name="password"/>
            </div>
            <div>
                <label for="password_confirmation" class="k-label">Confirmation</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="k-input"
                       autocomplete="new-password">
            </div>
        </div>
        <p class="k-hint">
            Minimum 12 caractères, avec majuscules, minuscules et chiffres.
            @if ($user) Laissez vide pour conserver le mot de passe actuel. @endif
        </p>
    </fieldset>
</div>

<fieldset class="k-fieldset mt-4">
    <legend class="k-fieldset-legend">
        <x-dme::icon name="calendar" class="h-4.5 w-4.5 text-clinic-600"/> Horaire hebdomadaire type
    </legend>
    <p class="k-hint mb-3">
        Créneau habituel de présence. Purement indicatif : il ne déclenche jamais la garde, et un jour sans
        horaire est traité comme un jour de repos.
    </p>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs font-semibold text-ink-500">
                    <th class="py-1 pr-2">Jour</th>
                    <th class="py-1 pr-2">Repos</th>
                    <th class="py-1 pr-2">Début</th>
                    <th class="py-1 pr-2">Fin</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($weekdays as $weekday => $label)
                    @php
                        $existing = $user?->weeklySchedules->firstWhere('weekday', $weekday);
                        $isOff = old("schedule.$weekday.is_off") !== null
                            ? (bool) old("schedule.$weekday.is_off")
                            : ($existing?->isRestDay() ?? false);
                    @endphp
                    <tr class="border-t border-ink-100 align-top">
                        <td class="py-1.5 pr-2 font-medium text-ink-700">{{ $label }}</td>
                        <td class="py-1.5 pr-2">
                            <input type="checkbox" name="schedule[{{ $weekday }}][is_off]" value="1"
                                   @checked($isOff) class="h-4 w-4 rounded border-ink-300 text-clinic-600"
                                   aria-label="{{ $label }} : repos">
                        </td>
                        <td class="py-1.5 pr-2">
                            <input type="time" name="schedule[{{ $weekday }}][starts_at]" aria-label="{{ $label }} : début"
                                   value="{{ old("schedule.$weekday.starts_at", $existing && ! $existing->isRestDay() ? substr($existing->starts_at, 0, 5) : null) }}"
                                   class="k-input">
                            <x-dme::field-error name="schedule.{{ $weekday }}.starts_at"/>
                        </td>
                        <td class="py-1.5 pr-2">
                            <input type="time" name="schedule[{{ $weekday }}][ends_at]" aria-label="{{ $label }} : fin"
                                   value="{{ old("schedule.$weekday.ends_at", $existing && ! $existing->isRestDay() ? substr($existing->ends_at, 0, 5) : null) }}"
                                   class="k-input">
                            <x-dme::field-error name="schedule.{{ $weekday }}.ends_at"/>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</fieldset>

<fieldset class="k-fieldset mt-4"
          x-data="dutyPeriodBuilder({{ Illuminate\Support\Js::from(old('duty_periods', $upcomingDutyPeriods->map(fn ($period) => [
              'starts_at' => $period->starts_at->format('Y-m-d\TH:i'),
              'ends_at' => $period->ends_at->format('Y-m-d\TH:i'),
              'notes' => $period->notes,
          ])->all())) }})">
    <legend class="k-fieldset-legend">
        <x-dme::icon name="calendar" class="h-4.5 w-4.5 text-clinic-600"/> Gardes planifiées à l'avance
    </legend>
    <p class="k-hint mb-3">
        Ces périodes prennent et terminent automatiquement la garde aux heures indiquées, en plus du bouton de
        prise de garde manuelle. Seules les gardes qui n'ont pas encore commencé peuvent être modifiées ici.
    </p>

    @if ($activeDutyPeriods->isNotEmpty())
        <div class="mb-3 rounded-lg bg-clinic-50 p-3">
            <p class="text-xs font-semibold text-clinic-800">Garde en cours</p>
            <ul class="mt-1 space-y-0.5 text-xs text-clinic-900">
                @foreach ($activeDutyPeriods as $period)
                    <li>
                        Du {{ $period->starts_at->translatedFormat('d M à H:i') }}
                        au {{ $period->ends_at->translatedFormat('d M à H:i') }}
                        @if ($period->notes) - {{ $period->notes }} @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <template x-for="(period, index) in periods" :key="index">
        <div class="mb-2 rounded-lg border border-ink-200 p-3">
            <div class="mb-2 flex items-center justify-between">
                <span class="text-xs font-semibold text-ink-500">
                    Garde <span x-text="index + 1"></span>
                </span>
                <button type="button" @click="remove(index)" class="k-btn-ghost k-btn-sm text-red-600"
                        aria-label="Retirer cette garde">
                    <x-dme::icon name="trash" class="h-4 w-4"/>
                </button>
            </div>
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="k-label" :for="'duty_starts_' + index">Début</label>
                    <input :id="'duty_starts_' + index" :name="`duty_periods[${index}][starts_at]`"
                           x-model="period.starts_at" type="datetime-local" class="k-input">
                </div>
                <div>
                    <label class="k-label" :for="'duty_ends_' + index">Fin</label>
                    <input :id="'duty_ends_' + index" :name="`duty_periods[${index}][ends_at]`"
                           x-model="period.ends_at" type="datetime-local" class="k-input">
                </div>
                <div>
                    <label class="k-label" :for="'duty_notes_' + index">Note</label>
                    <input :id="'duty_notes_' + index" :name="`duty_periods[${index}][notes]`"
                           x-model="period.notes" type="text" maxlength="500" class="k-input"
                           placeholder="Remplacement, astreinte...">
                </div>
            </div>
        </div>
    </template>

    <button type="button" @click="add()" class="k-btn-secondary">
        <x-dme::icon name="plus" class="h-4 w-4"/> Ajouter une garde planifiée
    </button>

    <x-dme::field-error name="duty_periods"/>
    @php $dutyPeriodErrors = collect($errors->keys())->filter(fn ($key) => str_starts_with($key, 'duty_periods.')); @endphp
    @if ($dutyPeriodErrors->isNotEmpty())
        <ul class="mt-2 space-y-0.5">
            @foreach ($dutyPeriodErrors as $key)
                <li class="k-error" role="alert">{{ $errors->first($key) }}</li>
            @endforeach
        </ul>
    @endif
</fieldset>
