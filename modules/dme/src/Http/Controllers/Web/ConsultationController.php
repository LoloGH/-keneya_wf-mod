<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Controllers\Web;

use Keneya\Dme\Http\Controllers\Controller;
use Keneya\Dme\Models\ClinicalNote;
use Keneya\Dme\Models\Consultation;
use Keneya\Dme\Models\Diagnosis;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\Service;
use Keneya\Dme\Services\Documents\PdfGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Consultation médicale (§19).
 *
 * Une consultation agrège en une seule transaction : motif, histoire de
 * la maladie, constantes, examen clinique par appareil, diagnostics et
 * plan de soins. Le tout est écrit atomiquement afin qu'une consultation
 * ne soit jamais persistée à moitié.
 */
class ConsultationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Consultation::class);

        $consultations = Consultation::query()
            ->with([
                'patient:id,patient_number,first_name,last_name,birth_date,sex',
                'doctor:id,name,first_name,last_name,title',
                'service:id,name',
            ])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q
                ->where(fn ($inner) => $inner
                    ->where('consultation_number', 'like', '%'.$term.'%')
                    ->orWhereHas('patient', fn ($p) => $p->search($term))))
            ->orderByDesc('started_at')
            ->paginate(config('dme.pagination.default'))
            ->withQueryString();

        return view('dme::consultations.index', [
            'consultations' => $consultations,
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function create(Patient $patient): View
    {
        $this->authorize('create', Consultation::class);

        return view('dme::consultations.create', [
            'patient' => $patient->load(['allergies', 'chronicConditions', 'attendingDoctor']),
            'services' => Service::where('is_active', true)->orderBy('name')->get(),
            'lastVitals' => $patient->vitalSigns()->first(),
        ]);
    }

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $this->authorize('create', Consultation::class);

        $data = $this->validated($request);

        $consultation = DB::transaction(function () use ($request, $patient, $data): Consultation {
            $consultation = $patient->consultations()->create([
                'doctor_id' => $request->user()->id,
                'service_id' => $data['service_id'] ?? null,
                'appointment_id' => $data['appointment_id'] ?? null,
                'started_at' => $data['started_at'],
                'type' => $data['type'],
                'status' => 'in_progress',
                'reason' => $data['reason'] ?? null,
                'history_of_illness' => $data['history_of_illness'] ?? null,
                'treatment_plan' => $data['treatment_plan'] ?? null,
                'follow_up' => $data['follow_up'] ?? null,
                'recommendations' => $data['recommendations'] ?? null,
            ]);

            $this->syncVitals($request, $consultation);
            $this->syncClinicalNotes($request, $consultation);
            $this->syncDiagnoses($request, $consultation);

            return $consultation;
        });

        // « Enregistrer et prescrire » enchaîne directement sur l'ordonnance (§19).
        if ($request->input('action') === 'prescribe') {
            return redirect()
                ->route('dme.prescriptions.create', ['patient' => $patient, 'consultation' => $consultation->id])
                ->with('success', 'Consultation '.$consultation->consultation_number.' enregistrée.');
        }

        return redirect()->route('dme.consultations.show', $consultation)
            ->with('success', 'Consultation '.$consultation->consultation_number.' enregistrée.');
    }

    public function show(Consultation $consultation): View
    {
        $this->authorize('view', $consultation);

        $consultation->load([
            'patient.allergies',
            'patient.chronicConditions',
            'doctor:id,name,first_name,last_name,title,speciality',
            'service:id,name',
            'vitalSigns',
            'clinicalNotes',
            'diagnoses.doctor:id,name,first_name,last_name,title',
            'prescriptions.items',
            'labOrders.items',
            'imagingOrders',
        ]);

        return view('dme::consultations.show', ['consultation' => $consultation]);
    }

    public function edit(Consultation $consultation): View
    {
        $this->authorize('update', $consultation);

        $consultation->load(['clinicalNotes', 'diagnoses', 'vitalSigns']);

        return view('dme::consultations.edit', [
            'consultation' => $consultation,
            'patient' => $consultation->patient()->with(['allergies', 'chronicConditions'])->firstOrFail(),
            'services' => Service::where('is_active', true)->orderBy('name')->get(),
            'lastVitals' => $consultation->vitalSigns->first(),
        ]);
    }

    public function update(Request $request, Consultation $consultation): RedirectResponse
    {
        $this->authorize('update', $consultation);

        $data = $this->validated($request);

        DB::transaction(function () use ($request, $consultation, $data): void {
            $consultation->update([
                'service_id' => $data['service_id'] ?? null,
                'started_at' => $data['started_at'],
                'type' => $data['type'],
                'reason' => $data['reason'] ?? null,
                'history_of_illness' => $data['history_of_illness'] ?? null,
                'treatment_plan' => $data['treatment_plan'] ?? null,
                'follow_up' => $data['follow_up'] ?? null,
                'recommendations' => $data['recommendations'] ?? null,
            ]);

            $this->syncVitals($request, $consultation);

            // L'examen clinique est remplacé en bloc tant que la
            // consultation est ouverte ; les diagnostics sont cumulatifs.
            $consultation->clinicalNotes()->delete();
            $this->syncClinicalNotes($request, $consultation);
            $this->syncDiagnoses($request, $consultation);
        });

        return redirect()->route('dme.consultations.show', $consultation)
            ->with('success', 'Consultation mise à jour.');
    }

    /**
     * Clôture la consultation : elle devient non modifiable (§40).
     */
    public function complete(Consultation $consultation): RedirectResponse
    {
        $this->authorize('complete', $consultation);

        $consultation->update([
            'status' => 'completed',
            'ended_at' => now(),
        ]);

        return redirect()->route('dme.consultations.show', $consultation)
            ->with('success', 'Consultation terminée. Elle n\'est plus modifiable.');
    }

    public function reportPdf(Consultation $consultation, PdfGenerator $pdf): Response
    {
        $this->authorize('view', $consultation);

        return response($pdf->consultationReport($consultation), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$consultation->consultation_number.'.pdf"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'service_id' => ['nullable', 'exists:dme_services,id'],
            'appointment_id' => ['nullable', 'exists:dme_appointments,id'],
            'started_at' => ['required', 'date', 'before_or_equal:now'],
            'type' => ['required', Rule::in(array_keys(Consultation::TYPES))],
            'reason' => ['nullable', 'string', 'max:1000'],
            'history_of_illness' => ['nullable', 'string', 'max:10000'],
            'treatment_plan' => ['nullable', 'string', 'max:10000'],
            'follow_up' => ['nullable', 'string', 'max:5000'],
            'recommendations' => ['nullable', 'string', 'max:5000'],

            'vitals' => ['nullable', 'array'],
            'vitals.temperature' => ['nullable', 'numeric', 'between:25,45'],
            'vitals.systolic' => ['nullable', 'integer', 'between:40,300'],
            'vitals.diastolic' => ['nullable', 'integer', 'between:20,200'],
            'vitals.heart_rate' => ['nullable', 'integer', 'between:20,250'],
            'vitals.respiratory_rate' => ['nullable', 'integer', 'between:5,80'],
            'vitals.oxygen_saturation' => ['nullable', 'integer', 'between:50,100'],
            'vitals.weight' => ['nullable', 'numeric', 'between:0.5,400'],
            'vitals.height' => ['nullable', 'numeric', 'between:20,250'],
            'vitals.glycemia' => ['nullable', 'numeric', 'between:0.1,10'],

            'exam' => ['nullable', 'array'],
            'exam.*' => ['nullable', 'string', 'max:5000'],

            'diagnoses' => ['nullable', 'array'],
            'diagnoses.*.label' => ['nullable', 'string', 'max:200'],
            'diagnoses.*.code' => ['nullable', 'string', 'max:20'],
            'diagnoses.*.type' => ['nullable', Rule::in(['primary', 'secondary', 'differential'])],
            'diagnoses.*.status' => ['nullable', Rule::in(array_keys(Diagnosis::STATUSES))],
            'diagnoses.*.comment' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'started_at' => 'date de consultation',
            'type' => 'type de consultation',
            'reason' => 'motif',
        ]);
    }

    private function syncVitals(Request $request, Consultation $consultation): void
    {
        $vitals = array_filter(
            (array) $request->input('vitals', []),
            fn ($value) => $value !== null && $value !== ''
        );

        if ($vitals === []) {
            return;
        }

        $consultation->vitalSigns()->create($vitals + [
            'patient_id' => $consultation->patient_id,
            'measured_at' => $consultation->started_at,
            'recorded_by' => $request->user()->id,
        ]);
    }

    private function syncClinicalNotes(Request $request, Consultation $consultation): void
    {
        foreach ((array) $request->input('exam', []) as $system => $content) {
            if (blank($content) || ! array_key_exists($system, ClinicalNote::SYSTEMS)) {
                continue;
            }

            $consultation->clinicalNotes()->create([
                'system' => $system,
                'content' => $content,
            ]);
        }
    }

    private function syncDiagnoses(Request $request, Consultation $consultation): void
    {
        foreach ((array) $request->input('diagnoses', []) as $row) {
            if (blank($row['label'] ?? null)) {
                continue;
            }

            $consultation->diagnoses()->create([
                'patient_id' => $consultation->patient_id,
                'doctor_id' => $request->user()->id,
                'label' => $row['label'],
                'code' => $row['code'] ?? null,
                'code_system' => 'icd10',
                'type' => $row['type'] ?? 'primary',
                'status' => $row['status'] ?? 'suspected',
                'diagnosed_on' => $consultation->started_at->toDateString(),
                'comment' => $row['comment'] ?? null,
            ]);
        }
    }
}
