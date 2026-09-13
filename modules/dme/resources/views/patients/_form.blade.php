@php
    /** Formulaire partagé création / modification d'un patient (§12). */
    $patient = $patient ?? null;
    $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
@endphp

<div class="grid gap-4 lg:grid-cols-2">

    {{-- Identité --}}
    <fieldset class="k-fieldset">
        <legend class="k-fieldset-legend">
            <x-dme::icon name="users" class="h-4.5 w-4.5 text-clinic-600"/> Identité
        </legend>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="last_name" class="k-label">Nom <span class="text-red-600" aria-hidden="true">*</span></label>
                <input id="last_name" name="last_name" type="text" required maxlength="100"
                       value="{{ old('last_name', $patient?->last_name) }}" class="k-input">
                <x-dme::field-error name="last_name"/>
            </div>
            <div>
                <label for="first_name" class="k-label">Prénom <span class="text-red-600" aria-hidden="true">*</span></label>
                <input id="first_name" name="first_name" type="text" required maxlength="100"
                       value="{{ old('first_name', $patient?->first_name) }}" class="k-input">
                <x-dme::field-error name="first_name"/>
            </div>
            <div>
                <label for="sex" class="k-label">Sexe <span class="text-red-600" aria-hidden="true">*</span></label>
                <select id="sex" name="sex" required class="k-select">
                    @foreach (['male' => 'Homme', 'female' => 'Femme', 'other' => 'Autre', 'unknown' => 'Non précisé'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('sex', $patient?->sex) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-dme::field-error name="sex"/>
            </div>
            <div>
                <label for="birth_date" class="k-label">Date de naissance</label>
                <input id="birth_date" name="birth_date" type="date" max="{{ now()->toDateString() }}"
                       value="{{ old('birth_date', $patient?->birth_date?->toDateString()) }}" class="k-input">
                <x-dme::field-error name="birth_date"/>
                <label class="mt-1.5 flex items-center gap-2 text-xs text-ink-500">
                    <input type="checkbox" name="birth_date_estimated" value="1"
                           @checked(old('birth_date_estimated', $patient?->birth_date_estimated))
                           class="h-3.5 w-3.5 rounded border-ink-300 text-clinic-600">
                    Date estimée (âge déclaré)
                </label>
            </div>
            <div>
                <label for="birth_place" class="k-label">Lieu de naissance</label>
                <input id="birth_place" name="birth_place" type="text" maxlength="150"
                       value="{{ old('birth_place', $patient?->birth_place) }}" class="k-input">
            </div>
            <div>
                <label for="nationality" class="k-label">Nationalité</label>
                <input id="nationality" name="nationality" type="text" maxlength="100"
                       value="{{ old('nationality', $patient?->nationality ?? 'Malienne') }}" class="k-input">
            </div>
            <div>
                <label for="marital_status" class="k-label">Situation familiale</label>
                <input id="marital_status" name="marital_status" type="text" maxlength="50"
                       value="{{ old('marital_status', $patient?->marital_status) }}" class="k-input">
            </div>
            <div>
                <label for="occupation" class="k-label">Profession</label>
                <input id="occupation" name="occupation" type="text" maxlength="100"
                       value="{{ old('occupation', $patient?->occupation) }}" class="k-input">
            </div>

            {{-- Le seul champ qui distingue deux personnes à coup sûr : le
                 téléphone change et se prête, le nom s'écrit de dix façons,
                 l'âge se donne à un an près. Facultatif, et il doit le rester,
                 un patient arrivé sans papiers ayant droit à un dossier. --}}
            <div class="sm:col-span-2">
                <label for="id_card_number" class="k-label">N° de la carte d'identité</label>
                <input id="id_card_number" name="id_card_number" type="text" maxlength="60"
                       value="{{ old('id_card_number', $patient?->id_card_number) }}" class="k-input"
                       autocomplete="off" placeholder="Recommandé : évite les doublons">
                <x-dme::field-error name="id_card_number"/>
            </div>
        </div>

        @if ($patient)
            <p class="k-hint">
                Numéro de dossier :
                <span class="font-mono font-medium text-ink-700">{{ $patient->patient_number }}</span>,
                attribué à la création et jamais modifié.
            </p>
        @else
            <p class="k-hint">
                Le numéro de dossier médical (format <span class="font-mono">PAT-{{ now()->format('Y') }}-000001</span>)
                est généré automatiquement à l'enregistrement.
            </p>
        @endif
    </fieldset>

    {{-- Coordonnées --}}
    <fieldset class="k-fieldset">
        <legend class="k-fieldset-legend">
            <x-dme::icon name="chat" class="h-4.5 w-4.5 text-clinic-600"/> Coordonnées
        </legend>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="phone" class="k-label">Téléphone</label>
                <input id="phone" name="phone" type="tel" maxlength="30"
                       value="{{ old('phone', $patient?->phone) }}" class="k-input" placeholder="+223 70 00 10 01">
                <p class="k-hint">Utilisé pour les rappels de rendez-vous par SMS.</p>
                <x-dme::field-error name="phone"/>
            </div>
            <div>
                <label for="phone_secondary" class="k-label">Téléphone secondaire</label>
                <input id="phone_secondary" name="phone_secondary" type="tel" maxlength="30"
                       value="{{ old('phone_secondary', $patient?->phone_secondary) }}" class="k-input">
            </div>
            <div class="sm:col-span-2">
                <label for="email" class="k-label">Adresse e-mail</label>
                <input id="email" name="email" type="email" maxlength="150"
                       value="{{ old('email', $patient?->email) }}" class="k-input">
                <x-dme::field-error name="email"/>
            </div>
            <div class="sm:col-span-2">
                <label for="address" class="k-label">Adresse</label>
                <input id="address" name="address" type="text" maxlength="255"
                       value="{{ old('address', $patient?->address) }}" class="k-input">
            </div>
            <div>
                <label for="city" class="k-label">Ville</label>
                <input id="city" name="city" type="text" maxlength="100"
                       value="{{ old('city', $patient?->city ?? 'Bamako') }}" class="k-input">
            </div>
            <div>
                <label for="country" class="k-label">Pays</label>
                <input id="country" name="country" type="text" maxlength="100"
                       value="{{ old('country', $patient?->country ?? 'Mali') }}" class="k-input">
            </div>
        </div>
    </fieldset>

    {{-- Contact d'urgence --}}
    <fieldset class="k-fieldset">
        <legend class="k-fieldset-legend">
            <x-dme::icon name="alert" class="h-4.5 w-4.5 text-amber-600"/> Personne à prévenir
        </legend>

        @if ($patient && $patient->relationLoaded('emergencyContacts') && $patient->emergencyContacts->isNotEmpty())
            <ul class="space-y-2">
                @foreach ($patient->emergencyContacts as $contact)
                    <li class="rounded-lg bg-ink-50 px-3 py-2 text-sm">
                        <span class="font-medium text-ink-900">{{ $contact->name }}</span>
                        <span class="text-ink-500">- {{ $contact->relationship ?: 'Proche' }}{{ $contact->phone ? ' · '.$contact->phone : '' }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="k-hint">
                Les accompagnateurs relevés à l'accueil figurent ici. Saisir ci-dessous
                le nom de l'un d'eux met son numéro à jour au lieu d'en créer un second.
            </p>
        @endif

        {{-- Toujours saisissable, y compris sur un dossier qui a déjà un
             contact : la personne à prévenir change avec la vie du patient, et
             renvoyer ailleurs pour ce seul champ n'apprend rien à personne. --}}
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="ec_name" class="k-label">Nom</label>
                <input id="ec_name" name="emergency_contact[name]" type="text" maxlength="150"
                       value="{{ old('emergency_contact.name') }}" class="k-input">
                <x-dme::field-error name="emergency_contact.name"/>
            </div>
            <div>
                <label for="ec_relationship" class="k-label">Relation</label>
                <input id="ec_relationship" name="emergency_contact[relationship]" type="text" maxlength="100"
                       value="{{ old('emergency_contact.relationship') }}" class="k-input" placeholder="Épouse, fils...">
            </div>
            <div>
                <label for="ec_phone" class="k-label">Téléphone</label>
                <input id="ec_phone" name="emergency_contact[phone]" type="tel" maxlength="30"
                       value="{{ old('emergency_contact.phone') }}" class="k-input">
                <x-dme::field-error name="emergency_contact.phone"/>
            </div>
        </div>
    </fieldset>

    {{-- Informations médicales --}}
    <fieldset class="k-fieldset">
        <legend class="k-fieldset-legend">
            <x-dme::icon name="heart" class="h-4.5 w-4.5 text-red-600"/> Informations médicales
        </legend>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="blood_group" class="k-label">Groupe sanguin</label>
                <select id="blood_group" name="blood_group" class="k-select">
                    <option value="">Inconnu</option>
                    @foreach ($bloodGroups as $group)
                        <option value="{{ $group }}" @selected(old('blood_group', $patient?->blood_group) === $group)>{{ $group }}</option>
                    @endforeach
                </select>
                <x-dme::field-error name="blood_group"/>
            </div>
            <div>
                <label for="attending_doctor_id" class="k-label">Médecin traitant</label>
                <select id="attending_doctor_id" name="attending_doctor_id" class="k-select">
                    <option value="">Non attribué</option>
                    @foreach ($doctors as $doctor)
                        <option value="{{ $doctor->id }}"
                            @selected((string) old('attending_doctor_id', $patient?->attending_doctor_id) === (string) $doctor->id)>
                            {{ $doctor->displayName() }}{{ $doctor->speciality ? ' - '.$doctor->speciality : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            @unless ($patient)
                <div>
                    <label for="known_allergies" class="k-label">Allergies connues</label>
                    <input id="known_allergies" name="known_allergies" type="text" maxlength="500"
                           value="{{ old('known_allergies') }}" class="k-input" placeholder="Pénicilline, arachide...">
                    <p class="k-hint">
                        Séparez par des virgules. La gravité se précise ensuite dans l'onglet Allergies -
                        une allergie sévère devient une alerte permanente du dossier.
                    </p>
                </div>
                <div>
                    <label for="chronic_conditions" class="k-label">Maladies chroniques</label>
                    <input id="chronic_conditions" name="chronic_conditions" type="text" maxlength="500"
                           value="{{ old('chronic_conditions') }}" class="k-input" placeholder="Hypertension, diabète type 2...">
                    <p class="k-hint">Séparez par des virgules.</p>
                </div>
            @endunless

            @if ($patient)
                <div>
                    <label for="status" class="k-label">Statut du dossier</label>
                    <select id="status" name="status" class="k-select">
                        @foreach (['active' => 'Actif', 'inactive' => 'Inactif', 'deceased' => 'Décédé', 'archived' => 'Archivé'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $patient->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="sm:col-span-2">
                <label for="notes" class="k-label">Notes administratives</label>
                <textarea id="notes" name="notes" rows="2" maxlength="2000" class="k-textarea">{{ old('notes', $patient?->notes) }}</textarea>
            </div>
        </div>
    </fieldset>
</div>
