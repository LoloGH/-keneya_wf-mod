<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Controllers\Api;

use Keneya\Dme\Http\Controllers\Controller;
use Keneya\Dme\Http\Resources\ConsultationResource;
use Keneya\Dme\Http\Resources\LabOrderResource;
use Keneya\Dme\Http\Resources\MedicalDocumentResource;
use Keneya\Dme\Http\Resources\PrescriptionResource;
use Keneya\Dme\Models\Consultation;
use Keneya\Dme\Models\LabOrder;
use Keneya\Dme\Models\MedicalDocument;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\Prescription;
use Keneya\Dme\Services\Prescriptions\AllergyChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * API REST : sous-ressources du dossier patient (§43).
 */
class PatientRecordApiController extends Controller
{
    // -----------------------------------------------------------------
    // Consultations
    // -----------------------------------------------------------------

    public function consultations(Patient $patient): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Consultation::class);
        $this->authorize('view', $patient);

        return ConsultationResource::collection(
            $patient->consultations()
                ->with(['doctor', 'service', 'diagnoses', 'vitalSigns'])
                ->paginate(25)
        );
    }

    public function storeConsultation(Request $request, Patient $patient): JsonResponse
    {
        $this->authorize('create', Consultation::class);

        $data = $request->validate([
            'started_at' => ['required', 'date'],
            'type' => ['required', Rule::in(array_keys(Consultation::TYPES))],
            'service_id' => ['nullable', 'exists:dme_services,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'history_of_illness' => ['nullable', 'string', 'max:10000'],
        ]);

        $consultation = $patient->consultations()->create($data + [
            'doctor_id' => $request->user()->id,
            'status' => 'in_progress',
        ]);

        return ConsultationResource::make($consultation)->response()->setStatusCode(201);
    }

    // -----------------------------------------------------------------
    // Ordonnances
    // -----------------------------------------------------------------

    public function prescriptions(Patient $patient): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Prescription::class);
        $this->authorize('view', $patient);

        return PrescriptionResource::collection(
            $patient->prescriptions()->with(['doctor', 'items'])->paginate(25)
        );
    }

    public function storePrescription(
        Request $request,
        Patient $patient,
        AllergyChecker $allergyChecker,
    ): JsonResponse {
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
            'items.*.duration' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['nullable', 'string', 'max:100'],
            'items.*.instructions' => ['nullable', 'string', 'max:500'],
        ]);

        $prescription = DB::transaction(function () use ($request, $patient, $data, $allergyChecker): Prescription {
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

            // Le contrôle d'allergie s'applique aussi aux créations via API :
            // aucun canal ne doit pouvoir contourner cette vérification (§22).
            $prescription->update([
                'allergy_warnings' => $allergyChecker->check($prescription) ?: null,
            ]);

            return $prescription;
        });

        return PrescriptionResource::make($prescription->load('items'))
            ->response()
            ->setStatusCode(201);
    }

    // -----------------------------------------------------------------
    // Laboratoire
    // -----------------------------------------------------------------

    public function laboratory(Patient $patient): AnonymousResourceCollection
    {
        $this->authorize('viewAny', LabOrder::class);
        $this->authorize('view', $patient);

        return LabOrderResource::collection(
            $patient->labOrders()->with(['doctor', 'items.results'])->paginate(25)
        );
    }

    public function storeLabOrder(Request $request): JsonResponse
    {
        $this->authorize('create', LabOrder::class);

        $data = $request->validate([
            'patient_id' => ['required', 'exists:dme_patients,id'],
            'consultation_id' => ['nullable', 'exists:dme_consultations,id'],
            'requested_at' => ['required', 'date'],
            'priority' => ['required', Rule::in(array_keys(LabOrder::PRIORITIES))],
            'indication' => ['nullable', 'string', 'max:1000'],
            'exams' => ['required', 'array', 'min:1'],
            'exams.*' => ['string', 'max:150'],
        ]);

        $order = DB::transaction(function () use ($request, $data): LabOrder {
            $order = LabOrder::create([
                'patient_id' => $data['patient_id'],
                'consultation_id' => $data['consultation_id'] ?? null,
                'doctor_id' => $request->user()->id,
                'requested_at' => $data['requested_at'],
                'priority' => $data['priority'],
                'indication' => $data['indication'] ?? null,
                'status' => 'requested',
            ]);

            foreach (array_unique($data['exams']) as $exam) {
                $order->items()->create(['exam_name' => $exam, 'status' => 'requested']);
            }

            return $order;
        });

        return LabOrderResource::make($order->load('items.results'))
            ->response()
            ->setStatusCode(201);
    }

    // -----------------------------------------------------------------
    // Documents
    // -----------------------------------------------------------------

    public function documents(Patient $patient): AnonymousResourceCollection
    {
        $this->authorize('viewAny', MedicalDocument::class);
        $this->authorize('view', $patient);

        return MedicalDocumentResource::collection(
            $patient->documents()->with('uploader')->paginate(25)
        );
    }

    public function storeDocument(
        Request $request,
        \Keneya\Dme\Services\Documents\DocumentStorage $storage,
    ): JsonResponse {
        $this->authorize('create', MedicalDocument::class);

        $data = $request->validate([
            'patient_id' => ['required', 'exists:dme_patients,id'],
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::in(array_keys(MedicalDocument::TYPES))],
            'description' => ['nullable', 'string', 'max:1000'],
            'file' => [
                'required', 'file',
                'max:'.config('dme.documents.max_size_kb'),
                'mimes:'.implode(',', config('dme.documents.allowed_mimes')),
            ],
        ]);

        $document = $storage->storeUploaded(
            Patient::findOrFail($data['patient_id']),
            $request->file('file'),
            [
                'title' => $data['title'],
                'type' => $data['type'],
                'description' => $data['description'] ?? null,
            ],
        );

        return MedicalDocumentResource::make($document)->response()->setStatusCode(201);
    }
}
