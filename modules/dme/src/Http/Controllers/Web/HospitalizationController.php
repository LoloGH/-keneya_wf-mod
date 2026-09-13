<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Controllers\Web;

use Keneya\Dme\Http\Controllers\Controller;
use Keneya\Dme\Models\Hospitalization;
use Keneya\Dme\Models\HospitalizationEvent;
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
 * Hospitalisation (§25) : admission, timeline de séjour et sortie.
 */
class HospitalizationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Hospitalization::class);

        $hospitalizations = Hospitalization::query()
            ->with([
                'patient:id,patient_number,first_name,last_name,birth_date,sex',
                'service:id,name',
                'doctor:id,name,first_name,last_name,title',
            ])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('service')->toString(), fn ($q, $s) => $q->where('service_id', $s))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q
                ->where(fn ($inner) => $inner
                    ->where('hospitalization_number', 'like', '%'.$term.'%')
                    ->orWhereHas('patient', fn ($p) => $p->search($term))))
            ->orderByDesc('admitted_at')
            ->paginate(config('dme.pagination.default'))
            ->withQueryString();

        return view('dme::hospitalizations.index', [
            'hospitalizations' => $hospitalizations,
            'filters' => $request->only(['q', 'status', 'service']),
            'services' => Service::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(Patient $patient): View
    {
        $this->authorize('create', Hospitalization::class);

        return view('dme::hospitalizations.create', [
            'patient' => $patient->load(['allergies', 'chronicConditions']),
            'services' => Service::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $this->authorize('create', Hospitalization::class);

        $data = $request->validate([
            'service_id' => ['nullable', 'exists:dme_services,id'],
            'admitted_at' => ['required', 'date', 'before_or_equal:now'],
            'admission_reason' => ['required', 'string', 'max:1000'],
            'admission_diagnosis' => ['nullable', 'string', 'max:200'],
            'room' => ['nullable', 'string', 'max:50'],
            'bed' => ['nullable', 'string', 'max:50'],
        ], [], [
            'admitted_at' => 'date d\'admission',
            'admission_reason' => 'motif d\'admission',
        ]);

        $hospitalization = DB::transaction(function () use ($request, $patient, $data): Hospitalization {
            $stay = $patient->hospitalizations()->create($data + [
                'doctor_id' => $request->user()->id,
                'status' => 'admitted',
            ]);

            // Le premier événement de la timeline est l'admission (§25).
            $stay->events()->create([
                'type' => 'admission',
                'occurred_at' => $stay->admitted_at,
                'title' => 'Admission dans le service',
                'content' => $stay->admission_reason,
                'recorded_by' => $request->user()->id,
            ]);

            return $stay;
        });

        return redirect()->route('dme.hospitalizations.show', $hospitalization)
            ->with('success', 'Admission '.$hospitalization->hospitalization_number.' enregistrée.');
    }

    public function show(Hospitalization $hospitalization): View
    {
        $this->authorize('view', $hospitalization);

        $hospitalization->load([
            'patient.allergies',
            'patient.chronicConditions',
            'service:id,name',
            'doctor:id,name,first_name,last_name,title',
            'events.recorder:id,name,first_name,last_name,title',
            'nursingNotes.nurse:id,name,first_name,last_name,title',
        ]);

        return view('dme::hospitalizations.show', [
            'hospitalization' => $hospitalization,
            'vitals' => $hospitalization->patient->vitalSigns()->limit(8)->get(),
        ]);
    }

    /**
     * Ajout d'un événement à la timeline du séjour (§25).
     */
    public function storeEvent(Request $request, Hospitalization $hospitalization): RedirectResponse
    {
        $this->authorize('update', $hospitalization);

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(HospitalizationEvent::TYPES))],
            'occurred_at' => ['required', 'date'],
            'title' => ['required', 'string', 'max:200'],
            'content' => ['nullable', 'string', 'max:5000'],
        ], [], ['type' => 'type d\'événement', 'title' => 'intitulé']);

        $hospitalization->events()->create($data + ['recorded_by' => $request->user()->id]);

        return back()->with('success', 'Événement ajouté au suivi du séjour.');
    }

    /**
     * Sortie du patient (§25).
     */
    public function discharge(Request $request, Hospitalization $hospitalization): RedirectResponse
    {
        $this->authorize('discharge', $hospitalization);

        $data = $request->validate([
            'discharged_at' => ['required', 'date', 'after:'.$hospitalization->admitted_at->toDateTimeString()],
            'discharge_diagnosis' => ['required', 'string', 'max:200'],
            'discharge_treatment' => ['nullable', 'string', 'max:10000'],
            'discharge_recommendations' => ['nullable', 'string', 'max:10000'],
            'discharge_summary' => ['nullable', 'string', 'max:20000'],
            'discharge_type' => ['required', Rule::in(['home', 'transfer', 'against_advice', 'deceased'])],
        ], [
            'discharged_at.after' => 'La date de sortie doit être postérieure à l\'admission.',
        ], [
            'discharged_at' => 'date de sortie',
            'discharge_diagnosis' => 'diagnostic de sortie',
            'discharge_type' => 'mode de sortie',
        ]);

        DB::transaction(function () use ($request, $hospitalization, $data): void {
            $hospitalization->update($data + ['status' => 'discharged']);

            $hospitalization->events()->create([
                'type' => 'discharge',
                'occurred_at' => $data['discharged_at'],
                'title' => 'Sortie - '.$data['discharge_diagnosis'],
                'content' => $data['discharge_recommendations'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Sortie enregistrée. Le compte rendu est disponible en PDF.');
    }

    public function pdf(Hospitalization $hospitalization, PdfGenerator $pdf): Response
    {
        $this->authorize('view', $hospitalization);

        return response($pdf->dischargeSummary($hospitalization), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$hospitalization->hospitalization_number.'.pdf"',
        ]);
    }
}
