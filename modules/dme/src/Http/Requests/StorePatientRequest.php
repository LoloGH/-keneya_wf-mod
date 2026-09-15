<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Keneya\Dme\Models\Patient;

/**
 * Validation de la création et de la modification d'un patient (§12).
 *
 * Toute la validation est côté serveur (§41) : les contraintes HTML du
 * formulaire ne sont qu'un confort de saisie.
 */
class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $patient = $this->route('patient');

        return $patient instanceof Patient
            ? $this->user()->can('update', $patient)
            : $this->user()->can('create', Patient::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'sex' => ['required', Rule::in(['male', 'female', 'other', 'unknown'])],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today', 'after:1900-01-01'],
            'birth_date_estimated' => ['nullable', 'boolean'],
            'birth_place' => ['nullable', 'string', 'max:150'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'marital_status' => ['nullable', 'string', 'max:50'],
            'occupation' => ['nullable', 'string', 'max:100'],
            // Recommandé, jamais exigé : un patient arrivé sans papiers doit
            // avoir un dossier. Pas d'unicité non plus — deux dossiers portant
            // le même numéro sont un doublon à examiner, pas une saisie à
            // rejeter devant quelqu'un qui attend.
            'id_card_number' => ['nullable', 'string', 'max:60'],

            'phone' => ['nullable', 'string', 'max:30'],
            'phone_secondary' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],

            'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'attending_doctor_id' => ['nullable', 'exists:users,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'deceased', 'archived'])],
            'notes' => ['nullable', 'string', 'max:2000'],

            // Contact d'urgence : facultatif à la création (§12)
            'emergency_contact.name' => ['nullable', 'string', 'max:150'],
            'emergency_contact.relationship' => ['nullable', 'string', 'max:100'],
            // Le téléphone n'est plus exigé avec le nom : l'accueil enregistre
            // des accompagnateurs sans numéro, et un nom accompagné d'un lien
            // de parenté dit déjà qui chercher dans la salle d'attente.
            'emergency_contact.phone' => ['nullable', 'string', 'max:30'],

            // Informations médicales de premier niveau
            'known_allergies' => ['nullable', 'string', 'max:500'],
            'chronic_conditions' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'last_name' => 'nom',
            'id_card_number' => "numéro de la carte d'identité",
            'first_name' => 'prénom',
            'sex' => 'sexe',
            'birth_date' => 'date de naissance',
            'phone' => 'téléphone',
            'email' => 'adresse e-mail',
            'blood_group' => 'groupe sanguin',
            'attending_doctor_id' => 'médecin traitant',
            'emergency_contact.phone' => 'téléphone du contact d\'urgence',
        ];
    }
}
