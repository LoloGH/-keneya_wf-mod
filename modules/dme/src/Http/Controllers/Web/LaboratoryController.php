<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Controllers\Web;

use Keneya\Dme\Http\Controllers\Controller;
use Keneya\Dme\Models\LabOrder;
use Keneya\Dme\Models\LabResult;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Services\Documents\PdfGenerator;
use Keneya\Dme\Services\Notifications\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Laboratoire (§23) : demandes d'analyse et saisie des résultats.
 *
 * Les deux actes sont séparés par les permissions : le médecin prescrit,
 * le laboratoire exécute et valide. Un résultat marqué critique déclenche
 * une alerte immédiate vers le prescripteur (§33).
 */
class LaboratoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', LabOrder::class);

        $orders = LabOrder::query()
            ->with([
                'patient:id,patient_number,first_name,last_name',
                'doctor:id,name,first_name,last_name,title',
                'items',
            ])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('priority')->toString(), fn ($q, $p) => $q->where('priority', $p))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q
                ->where(fn ($inner) => $inner
                    ->where('order_number', 'like', '%'.$term.'%')
                    ->orWhereHas('patient', fn ($p) => $p->search($term))))
            ->orderByDesc('requested_at')
            ->paginate(config('dme.pagination.default'))
            ->withQueryString();

        return view('dme::laboratory.index', [
            'orders' => $orders,
            'filters' => $request->only(['q', 'status', 'priority']),
        ]);
    }

    public function create(Request $request, Patient $patient): View
    {
        $this->authorize('create', LabOrder::class);

        return view('dme::laboratory.create', [
            'patient' => $patient,
            'consultationId' => $request->integer('consultation') ?: null,
            'catalogue' => $this->catalogue(),
        ]);
    }

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $this->authorize('create', LabOrder::class);

        $data = $request->validate([
            'consultation_id' => ['nullable', 'exists:dme_consultations,id'],
            'requested_at' => ['required', 'date'],
            'priority' => ['required', Rule::in(array_keys(LabOrder::PRIORITIES))],
            'indication' => ['nullable', 'string', 'max:1000'],
            'exams' => ['required', 'array', 'min:1'],
            'exams.*' => ['string', 'max:150'],
        ], [
            'exams.required' => 'Sélectionnez au moins un examen.',
        ], [
            'requested_at' => 'date de demande',
            'priority' => 'urgence',
        ]);

        $order = DB::transaction(function () use ($request, $patient, $data): LabOrder {
            $order = $patient->labOrders()->create([
                'consultation_id' => $data['consultation_id'] ?? null,
                'doctor_id' => $request->user()->id,
                'requested_at' => $data['requested_at'],
                'priority' => $data['priority'],
                'indication' => $data['indication'] ?? null,
                'status' => 'requested',
            ]);

            foreach (array_unique($data['exams']) as $exam) {
                $order->items()->create([
                    'exam_name' => $exam,
                    'category' => $this->categoryFor($exam),
                    'status' => 'requested',
                ]);
            }

            return $order;
        });

        return redirect()->route('dme.laboratory.show', $order)
            ->with('success', 'Demande '.$order->order_number.' créée.');
    }

    public function show(LabOrder $labOrder): View
    {
        $this->authorize('view', $labOrder);

        $labOrder->load([
            'patient:id,patient_number,first_name,last_name,birth_date,sex',
            'doctor:id,name,first_name,last_name,title',
            'items.results.validator:id,name,first_name,last_name,title',
        ]);

        // Les comptes rendus verses au dossier avec cette demande. Meme
        // rattachement que pour l'imagerie : la source du document designe la
        // demande, ce qui evite de les chercher par date ou par titre.
        $documents = $labOrder->patient->documents()
            ->where('source_type', LabOrder::class)
            ->where('source_id', $labOrder->id)
            ->get();

        return view('dme::laboratory.show', [
            'order' => $labOrder,
            'documents' => $documents,
        ]);
    }

    /**
     * Saisie des résultats par le laboratoire.
     */
    public function storeResults(
        Request $request,
        LabOrder $labOrder,
        NotificationService $notifications,
    ): RedirectResponse {
        $this->authorize('recordResult', $labOrder);

        $data = $request->validate([
            'results' => ['required', 'array', 'min:1'],
            'results.*.lab_order_item_id' => ['required', 'exists:dme_lab_order_items,id'],
            'results.*.parameter' => ['required', 'string', 'max:150'],
            'results.*.value' => ['nullable', 'string', 'max:100'],
            'results.*.unit' => ['nullable', 'string', 'max:50'],
            'results.*.reference_range' => ['nullable', 'string', 'max:100'],
            'results.*.flag' => ['required', Rule::in(['normal', 'low', 'high', 'critical'])],
            'results.*.comment' => ['nullable', 'string', 'max:500'],
        ]);

        $itemIds = $labOrder->items->pluck('id');
        $hasCritical = false;

        DB::transaction(function () use ($request, $labOrder, $data, $itemIds, &$hasCritical): void {
            foreach ($data['results'] as $row) {
                // Contrôle d'appartenance : un résultat ne peut être
                // rattaché qu'à un examen de CETTE demande (protection IDOR, §57).
                if (! $itemIds->contains((int) $row['lab_order_item_id'])) {
                    abort(403, 'Cet examen n\'appartient pas à la demande.');
                }

                if (blank($row['value'] ?? null)) {
                    continue;
                }

                LabResult::create([
                    'lab_order_item_id' => $row['lab_order_item_id'],
                    'patient_id' => $labOrder->patient_id,
                    'parameter' => $row['parameter'],
                    'value' => $row['value'],
                    'unit' => $row['unit'] ?? null,
                    'reference_range' => $row['reference_range'] ?? null,
                    'flag' => $row['flag'],
                    'comment' => $row['comment'] ?? null,
                    'measured_at' => now(),
                    'performed_by' => $request->user()->id,
                ]);

                $hasCritical = $hasCritical || $row['flag'] === 'critical';
            }

            $labOrder->items()->update(['status' => 'available']);
            $labOrder->update(['status' => 'available', 'completed_at' => now()]);
        });

        $notifications->labResultAvailable($labOrder);

        if ($hasCritical) {
            foreach ($data['results'] as $row) {
                if ($row['flag'] === 'critical') {
                    $notifications->criticalResult($labOrder, $row['parameter'], $row['value'] ?? null);
                }
            }
        }

        return back()->with('success', 'Résultats enregistrés. Le prescripteur a été notifié.');
    }

    /**
     * Validation biologique de l'ensemble des résultats.
     */
    public function validateResults(Request $request, LabOrder $labOrder): RedirectResponse
    {
        $this->authorize('validateResults', $labOrder);

        DB::transaction(function () use ($request, $labOrder): void {
            // Mise à jour ciblée par les examens de la demande : une
            // relation « hasManyThrough » ne peut pas être mise à jour
            // directement de façon portable entre SQLite et MySQL.
            LabResult::whereIn('lab_order_item_id', $labOrder->items->pluck('id'))
                ->update([
                    'validated_by' => $request->user()->id,
                    'validated_at' => now(),
                ]);

            $labOrder->items()->update(['status' => 'validated']);
            $labOrder->update(['status' => 'validated']);
        });

        return back()->with('success', 'Résultats validés.');
    }

    public function pdf(LabOrder $labOrder, PdfGenerator $pdf): Response
    {
        $this->authorize('view', $labOrder);

        return response($pdf->labReport($labOrder), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$labOrder->order_number.'.pdf"',
        ]);
    }

    /**
     * Catalogue d'examens proposé à la prescription.
     *
     * Volontairement porté par le code en phase 1 : un référentiel
     * paramétrable (LOINC) est prévu à la feuille de route, mais ne se
     * justifie pas tant que le circuit n'est pas validé.
     *
     * @return array<string, list<string>>
     */
    private function catalogue(): array
    {
        return [
            'Hématologie' => [
                'Numération formule sanguine', 'Vitesse de sédimentation',
                'Groupe sanguin - Rhésus', 'Taux de prothrombine',
            ],
            'Biochimie' => [
                'Glycémie à jeun', 'Hémoglobine glyquée (HbA1c)', 'Créatininémie',
                'Urée sanguine', 'Bilan lipidique', 'Transaminases (ASAT/ALAT)',
                'Ionogramme sanguin',
            ],
            'Immuno-sérologie' => [
                'CRP', 'Sérologie VIH', 'Sérologie hépatite B', 'Sérologie hépatite C',
            ],
            'Parasitologie / Bactériologie' => [
                'Goutte épaisse, paludisme', 'Test de diagnostic rapide paludisme',
                'Examen cytobactériologique des urines', 'Coproculture',
            ],
        ];
    }

    private function categoryFor(string $exam): ?string
    {
        foreach ($this->catalogue() as $category => $exams) {
            if (in_array($exam, $exams, true)) {
                return $category;
            }
        }

        return null;
    }
}
