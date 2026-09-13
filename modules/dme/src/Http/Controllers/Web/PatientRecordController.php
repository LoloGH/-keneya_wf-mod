<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Controllers\Web;

use Keneya\Dme\Http\Controllers\Controller;
use Keneya\Dme\Models\Allergy;
use Keneya\Dme\Models\ChronicCondition;
use Keneya\Dme\Models\Medication;
use Keneya\Dme\Models\MedicalHistory;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\VitalSign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Saisie des éléments du dossier depuis les onglets du DME
 * (antécédents, allergies, pathologies, traitements, constantes) : §16-20.
 *
 * Chaque action vérifie la policy correspondante avant écriture : les
 * permissions ne sont jamais présumées à partir de l'affichage.
 */
class PatientRecordController extends Controller
{
    public function storeHistory(Request $request, Patient $patient): RedirectResponse
    {
        $this->authorize('update', $patient);

        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(MedicalHistory::CATEGORIES))],
            'label' => ['required', 'string', 'max:200'],
            'code' => ['nullable', 'string', 'max:20'],
            'year' => ['nullable', 'string', 'max:9'],
            'occurred_on' => ['nullable', 'date', 'before_or_equal:today'],
            'facility' => ['nullable', 'string', 'max:150'],
            'relative' => ['nullable', 'string', 'max:100'],
            'complications' => ['nullable', 'string', 'max:1000'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ], [], ['label' => 'intitulé', 'category' => 'catégorie']);

        $patient->medicalHistories()->create($data + ['recorded_by' => $request->user()->id]);

        return back()->with('success', 'Antécédent ajouté au dossier.');
    }

    public function storeAllergy(Request $request, Patient $patient): RedirectResponse
    {
        $this->authorize('update', $patient);

        $data = $request->validate([
            'allergen' => ['required', 'string', 'max:150'],
            'allergen_type' => ['nullable', Rule::in(['medication', 'food', 'environment', 'other'])],
            'reaction' => ['nullable', 'string', 'max:255'],
            'severity' => ['required', Rule::in(array_keys(Allergy::SEVERITIES))],
            'observed_on' => ['nullable', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::in(['active', 'resolved', 'refuted'])],
            'comment' => ['nullable', 'string', 'max:1000'],
        ], [], ['allergen' => 'allergène', 'severity' => 'gravité']);

        $allergy = $patient->allergies()->create($data + ['recorded_by' => $request->user()->id]);

        // Une allergie sévère devient immédiatement une alerte permanente
        // du dossier (§17) et sera confrontée à toute nouvelle ordonnance.
        $message = $allergy->isCritical()
            ? 'Allergie enregistrée : elle apparaît désormais en alerte du dossier.'
            : 'Allergie enregistrée.';

        return back()->with('success', $message);
    }

    public function storeCondition(Request $request, Patient $patient): RedirectResponse
    {
        $this->authorize('update', $patient);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:200'],
            'code' => ['nullable', 'string', 'max:20'],
            'diagnosed_on' => ['nullable', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::in(['active', 'controlled', 'resolved'])],
            'comment' => ['nullable', 'string', 'max:1000'],
        ], [], ['label' => 'pathologie']);

        $patient->chronicConditions()->create($data + ['recorded_by' => $request->user()->id]);

        return back()->with('success', 'Pathologie chronique enregistrée.');
    }

    public function storeMedication(Request $request, Patient $patient): RedirectResponse
    {
        $this->authorize('update', $patient);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'dosage' => ['nullable', 'string', 'max:100'],
            'frequency' => ['nullable', 'string', 'max:100'],
            'route' => ['nullable', 'string', 'max:50'],
            'started_on' => ['nullable', 'date'],
            'ended_on' => ['nullable', 'date', 'after_or_equal:started_on'],
            'status' => ['required', Rule::in(array_keys(Medication::STATUSES))],
            'comment' => ['nullable', 'string', 'max:1000'],
        ], [], ['name' => 'médicament']);

        $patient->medications()->create($data + ['prescriber_id' => $request->user()->id]);

        return back()->with('success', 'Traitement habituel enregistré.');
    }

    /**
     * Relevé de constantes (§20).
     *
     * Historisation (§40) : chaque saisie crée un nouvel enregistrement.
     * Aucune valeur antérieure n'est écrasée, ce qui alimente les courbes
     * d'évolution du dossier.
     */
    public function storeVitalSign(Request $request, Patient $patient): RedirectResponse
    {
        $this->authorize('create', VitalSign::class);

        $data = $request->validate([
            'measured_at' => ['required', 'date', 'before_or_equal:now'],
            'temperature' => ['nullable', 'numeric', 'between:25,45'],
            'systolic' => ['nullable', 'integer', 'between:40,300'],
            'diastolic' => ['nullable', 'integer', 'between:20,200'],
            'heart_rate' => ['nullable', 'integer', 'between:20,250'],
            'respiratory_rate' => ['nullable', 'integer', 'between:5,80'],
            'oxygen_saturation' => ['nullable', 'integer', 'between:50,100'],
            'weight' => ['nullable', 'numeric', 'between:0.5,400'],
            'height' => ['nullable', 'numeric', 'between:20,250'],
            'glycemia' => ['nullable', 'numeric', 'between:0.1,10'],
            'pain_scale' => ['nullable', 'integer', 'between:0,10'],
            'consultation_id' => ['nullable', 'exists:dme_consultations,id'],
            'comment' => ['nullable', 'string', 'max:500'],
        ], [], [
            'measured_at' => 'date de mesure',
            'temperature' => 'température',
            'systolic' => 'tension systolique',
            'diastolic' => 'tension diastolique',
            'weight' => 'poids',
            'height' => 'taille',
        ]);

        $patient->vitalSigns()->create($data + ['recorded_by' => $request->user()->id]);

        return back()->with('success', 'Constantes enregistrées.');
    }

    public function storeEmergencyContact(Request $request, Patient $patient): RedirectResponse
    {
        $this->authorize('update', $patient);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'relationship' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
        ], [], ['name' => 'nom', 'phone' => 'téléphone']);

        $patient->emergencyContacts()->create($data);

        return back()->with('success', 'Contact d\'urgence ajouté.');
    }
}
