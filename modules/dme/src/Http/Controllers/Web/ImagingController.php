<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Controllers\Web;

use Keneya\Dme\Http\Controllers\Controller;
use Keneya\Dme\Models\ImagingOrder;
use Keneya\Dme\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Imagerie médicale (§24).
 *
 * Préparation DICOM/PACS (§46) : un « accession number » est attribué à
 * chaque demande. C'est la clé qu'utiliserait une future passerelle pour
 * rapprocher l'examen de son étude DICOM. Aucun PACS n'est implémenté :
 * les images restent des documents attachés au dossier.
 */
class ImagingController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ImagingOrder::class);

        $orders = ImagingOrder::query()
            ->with([
                'patient:id,patient_number,first_name,last_name',
                'doctor:id,name,first_name,last_name,title',
                'report',
            ])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('modality')->toString(), fn ($q, $m) => $q->where('modality', $m))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q
                ->where(fn ($inner) => $inner
                    ->where('order_number', 'like', '%'.$term.'%')
                    ->orWhereHas('patient', fn ($p) => $p->search($term))))
            ->orderByDesc('requested_at')
            ->paginate(config('dme.pagination.default'))
            ->withQueryString();

        return view('dme::imaging.index', [
            'orders' => $orders,
            'filters' => $request->only(['q', 'status', 'modality']),
        ]);
    }

    public function create(Request $request, Patient $patient): View
    {
        $this->authorize('create', ImagingOrder::class);

        return view('dme::imaging.create', [
            'patient' => $patient,
            'consultationId' => $request->integer('consultation') ?: null,
        ]);
    }

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $this->authorize('create', ImagingOrder::class);

        $data = $request->validate([
            'consultation_id' => ['nullable', 'exists:dme_consultations,id'],
            'modality' => ['required', Rule::in(array_keys(ImagingOrder::MODALITIES))],
            'body_site' => ['nullable', 'string', 'max:150'],
            'requested_at' => ['required', 'date'],
            'scheduled_for' => ['nullable', 'date', 'after_or_equal:requested_at'],
            'priority' => ['required', Rule::in(['routine', 'urgent', 'vital'])],
            'indication' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'modality' => 'type d\'examen',
            'body_site' => 'région explorée',
            'requested_at' => 'date de demande',
        ]);

        $order = $patient->imagingOrders()->create($data + [
            'doctor_id' => $request->user()->id,
            'status' => 'requested',
            // Identifiant d'accession, point d'accroche DICOM futur (§46)
            'accession_number' => 'ACC-'.now()->format('Y').'-'.Str::upper(Str::random(8)),
        ]);

        return redirect()->route('dme.imaging.show', $order)
            ->with('success', 'Demande d\'imagerie '.$order->order_number.' créée.');
    }

    public function show(ImagingOrder $imagingOrder): View
    {
        $this->authorize('view', $imagingOrder);

        $imagingOrder->load([
            'patient:id,patient_number,first_name,last_name,birth_date,sex',
            'doctor:id,name,first_name,last_name,title',
            'report.radiologist:id,name,first_name,last_name,title',
        ]);

        $documents = $imagingOrder->patient->documents()
            ->where('source_type', ImagingOrder::class)
            ->where('source_id', $imagingOrder->id)
            ->get();

        return view('dme::imaging.show', [
            'order' => $imagingOrder,
            'documents' => $documents,
        ]);
    }

    /**
     * Compte rendu rédigé par le radiologue (§24).
     */
    public function storeReport(Request $request, ImagingOrder $imagingOrder): RedirectResponse
    {
        $this->authorize('report', $imagingOrder);

        $data = $request->validate([
            'technique' => ['nullable', 'string', 'max:5000'],
            'findings' => ['required', 'string', 'max:20000'],
            'conclusion' => ['required', 'string', 'max:5000'],
            'is_abnormal' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['draft', 'final', 'amended'])],
        ], [], [
            'findings' => 'résultats',
            'conclusion' => 'conclusion',
        ]);

        // Un compte rendu existant est mis à jour plutôt que dupliqué ;
        // son statut « amended » conserve la trace d'une rectification.
        $imagingOrder->report()->updateOrCreate(
            ['imaging_order_id' => $imagingOrder->id],
            $data + [
                'patient_id' => $imagingOrder->patient_id,
                'radiologist_id' => $request->user()->id,
                'reported_at' => now(),
            ],
        );

        $imagingOrder->update([
            'status' => $data['status'] === 'draft' ? 'performed' : 'reported',
        ]);

        return back()->with('success', 'Compte rendu enregistré.');
    }
}
