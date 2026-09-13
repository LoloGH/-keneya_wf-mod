<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Controllers\Web;

use Keneya\Dme\Http\Controllers\Controller;
use Keneya\Dme\Models\Consultation;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\Prescription;
use Keneya\Dme\Services\Documents\PdfGenerator;
use Keneya\Dme\Services\Notifications\NotificationService;
use Keneya\Dme\Services\Prescriptions\AllergyChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Ordonnances (§22).
 *
 * Le contrôle d'allergie est exécuté à l'enregistrement puis rejoué à la
 * validation. Il produit un avertissement tracé sur l'ordonnance : aucune
 * ligne n'est jamais retirée automatiquement, la décision reste au
 * prescripteur, qui peut être invité à justifier son choix.
 */
class PrescriptionController extends Controller
{
    public function __construct(private readonly AllergyChecker $allergyChecker)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Prescription::class);

        $prescriptions = Prescription::query()
            ->with([
                'patient:id,patient_number,first_name,last_name',
                'doctor:id,name,first_name,last_name,title',
                'items',
            ])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q
                ->where(fn ($inner) => $inner
                    ->where('prescription_number', 'like', '%'.$term.'%')
                    ->orWhereHas('patient', fn ($p) => $p->search($term))))
            ->orderByDesc('issued_on')
            ->paginate(config('dme.pagination.default'))
            ->withQueryString();

        return view('dme::prescriptions.index', [
            'prescriptions' => $prescriptions,
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function create(Request $request, Patient $patient): View
    {
        $this->authorize('create', Prescription::class);

        $consultation = $request->filled('consultation')
            ? Consultation::where('patient_id', $patient->id)->find($request->integer('consultation'))
            : null;

        return view('dme::prescriptions.create', [
            'patient' => $patient->load(['allergies', 'chronicConditions']),
            'consultation' => $consultation,
            'usualMedications' => $patient->medications()->active()->get(),
        ]);
    }

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $this->authorize('create', Prescription::class);

        $data = $request->validate([
            'consultation_id' => ['nullable', 'exists:dme_consultations,id'],
            'issued_on' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.medication_name' => ['required', 'string', 'max:200'],
            'items.*.dosage' => ['nullable', 'string', 'max:100'],
            'items.*.form' => ['nullable', 'string', 'max:100'],
            'items.*.route' => ['nullable', 'string', 'max:50'],
            'items.*.frequency' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['nullable', 'string', 'max:100'],
            'items.*.duration' => ['nullable', 'string', 'max:100'],
            'items.*.instructions' => ['nullable', 'string', 'max:500'],
        ], [
            'items.required' => 'Une ordonnance doit comporter au moins un médicament.',
            'items.*.medication_name.required' => 'Le nom du médicament est obligatoire.',
        ], [
            'issued_on' => 'date de prescription',
            'valid_until' => 'date de fin de validité',
        ]);

        $prescription = DB::transaction(function () use ($request, $patient, $data): Prescription {
            $prescription = $patient->prescriptions()->create([
                'consultation_id' => $data['consultation_id'] ?? null,
                'doctor_id' => $request->user()->id,
                'issued_on' => $data['issued_on'],
                'valid_until' => $data['valid_until'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'status' => 'draft',
            ]);

            foreach (array_values($data['items']) as $position => $item) {
                $prescription->items()->create($item + ['position' => $position + 1]);
            }

            // Contrôle d'allergie enregistré dès le brouillon (§22).
            $warnings = $this->allergyChecker->check($prescription);
            $prescription->update(['allergy_warnings' => $warnings ?: null]);

            return $prescription;
        });

        $message = $prescription->hasAllergyWarnings()
            ? 'Ordonnance enregistrée : une allergie connue a été détectée, vérifiez avant validation.'
            : 'Ordonnance '.$prescription->prescription_number.' enregistrée.';

        return redirect()->route('dme.prescriptions.show', $prescription)
            ->with($prescription->hasAllergyWarnings() ? 'warning' : 'success', $message);
    }

    public function show(Prescription $prescription): View
    {
        $this->authorize('view', $prescription);

        $prescription->load([
            'patient.allergies',
            'doctor:id,name,first_name,last_name,title,speciality',
            'validator:id,name,first_name,last_name,title',
            'items',
            'consultation:id,consultation_number',
        ]);

        return view('dme::prescriptions.show', ['prescription' => $prescription]);
    }

    /**
     * Validation par le prescripteur.
     *
     * Le contrôle d'allergie est rejoué : si une correspondance existe,
     * l'utilisateur doit confirmer explicitement (case à cocher) et peut
     * justifier sa décision. L'application n'arbitre pas à sa place.
     */
    public function validatePrescription(
        Request $request,
        Prescription $prescription,
        NotificationService $notifications,
    ): RedirectResponse {
        $this->authorize('validate', $prescription);

        $warnings = $this->allergyChecker->check($prescription);

        $data = $request->validate([
            'acknowledge_allergy' => [$warnings === [] ? 'nullable' : 'accepted'],
            'allergy_justification' => ['nullable', 'string', 'max:1000'],
        ], [
            'acknowledge_allergy.accepted' => 'Vous devez confirmer avoir pris connaissance de l\'alerte allergie.',
        ]);

        $prescription->update([
            'status' => 'validated',
            'validated_by' => $request->user()->id,
            'validated_at' => now(),
            'allergy_warnings' => $warnings ?: null,
            'allergy_warning_acknowledged' => $warnings !== [],
            'allergy_warning_justification' => $data['allergy_justification'] ?? null,
        ]);

        $notifications->prescriptionValidated($prescription);

        return back()->with('success', 'Ordonnance validée. Le patient et la pharmacie ont été informés.');
    }

    public function dispense(Request $request, Prescription $prescription): RedirectResponse
    {
        $this->authorize('dispense', $prescription);

        $prescription->update([
            'status' => 'dispensed',
            'dispensed_by' => $request->user()->id,
            'dispensed_at' => now(),
        ]);

        return back()->with('success', 'Ordonnance marquée comme délivrée.');
    }

    /**
     * PDF de l'ordonnance, avec QR code de vérification (§47).
     */
    public function pdf(Request $request, Prescription $prescription, PdfGenerator $pdf): Response
    {
        $this->authorize('print', $prescription);

        $contents = $pdf->prescription($prescription);

        // « Archiver » verse le PDF au dossier du patient comme document.
        if ($request->boolean('archive') && $request->user()->can('documents.upload')) {
            $pdf->archive(
                $prescription->patient,
                $contents,
                'Ordonnance '.$prescription->prescription_number,
                'prescription',
                $prescription,
            );
        }

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$prescription->prescription_number.'.pdf"',
        ]);
    }
}
