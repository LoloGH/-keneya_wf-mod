<?php

declare(strict_types=1);

namespace Keneya\Dme\Services\Patients;

use Keneya\Dme\Models\Patient;
use Illuminate\Support\Collection;

/**
 * Construit l'historique médical chronologique du patient (§29).
 *
 * Chaque type d'événement est chargé séparément puis fusionné en une
 * seule ligne de temps triée du plus récent au plus ancien, groupée par
 * jour pour l'affichage.
 *
 * Performance (§58) : les requêtes sont bornées par `$limit` et
 * n'utilisent que des colonnes indexées (`patient_id` + date). Le
 * chargement progressif de l'historique se fait par incréments de cette
 * limite, ce qui garde le dossier réactif même après plusieurs années.
 */
class MedicalTimeline
{
    /** @var list<string> */
    public const FILTERS = [
        'consultations', 'diagnoses', 'prescriptions',
        'laboratory', 'imaging', 'hospitalizations', 'documents',
    ];

    /** @var array<string, string> */
    public const FILTER_LABELS = [
        'consultations' => 'Consultations',
        'diagnoses' => 'Diagnostics',
        'prescriptions' => 'Ordonnances',
        'laboratory' => 'Laboratoire',
        'imaging' => 'Imagerie',
        'hospitalizations' => 'Hospitalisations',
        'documents' => 'Documents',
    ];

    /**
     * Événements bruts triés par date décroissante.
     *
     * @param  list<string>  $filters  types à inclure ; vide = tous
     * @return Collection<int, array{type: string, label: string, date: \Illuminate\Support\Carbon, title: string, detail: string, status: string|null, url: string|null}>
     */
    public function build(Patient $patient, array $filters = [], int $limit = 40): Collection
    {
        $active = $filters === [] ? self::FILTERS : array_intersect($filters, self::FILTERS);
        $events = collect();

        if (in_array('consultations', $active, true)) {
            $events = $events->merge($this->consultations($patient, $limit));
        }

        if (in_array('diagnoses', $active, true)) {
            $events = $events->merge($this->diagnoses($patient, $limit));
        }

        if (in_array('prescriptions', $active, true)) {
            $events = $events->merge($this->prescriptions($patient, $limit));
        }

        if (in_array('laboratory', $active, true)) {
            $events = $events->merge($this->laboratory($patient, $limit));
        }

        if (in_array('imaging', $active, true)) {
            $events = $events->merge($this->imaging($patient, $limit));
        }

        if (in_array('hospitalizations', $active, true)) {
            $events = $events->merge($this->hospitalizations($patient, $limit));
        }

        if (in_array('documents', $active, true)) {
            $events = $events->merge($this->documents($patient, $limit));
        }

        return $events
            ->filter(fn (array $event) => $event['date'] !== null)
            ->sortByDesc(fn (array $event) => $event['date']->timestamp)
            ->take($limit)
            ->values();
    }

    /**
     * Historique groupé par journée, prêt pour l'affichage en timeline.
     *
     * @param  list<string>  $filters
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    public function grouped(Patient $patient, array $filters = [], int $limit = 40): Collection
    {
        return $this->build($patient, $filters, $limit)
            ->groupBy(fn (array $event) => $event['date']->format('Y-m-d'));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function consultations(Patient $patient, int $limit): Collection
    {
        return $patient->consultations()->with('doctor')->limit($limit)->get()
            ->map(fn ($consultation) => [
                'type' => 'consultations',
                'label' => 'Consultation',
                'date' => $consultation->started_at,
                'title' => $consultation->reason ?: $consultation->typeLabel(),
                'detail' => $consultation->doctor?->displayName() ?? 'Praticien non renseigné',
                'status' => $consultation->statusLabel(),
                'url' => route('dme.consultations.show', $consultation),
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function diagnoses(Patient $patient, int $limit): Collection
    {
        return $patient->diagnoses()->with('doctor')->latest('diagnosed_on')->limit($limit)->get()
            ->map(fn ($diagnosis) => [
                'type' => 'diagnoses',
                'label' => 'Diagnostic',
                'date' => $diagnosis->diagnosed_on ?? $diagnosis->created_at,
                'title' => $diagnosis->label,
                'detail' => trim(($diagnosis->code ? $diagnosis->code.' · ' : '').($diagnosis->doctor?->displayName() ?? '')),
                'status' => $diagnosis->statusLabel(),
                'url' => $diagnosis->consultation_id
                    ? route('dme.consultations.show', $diagnosis->consultation_id)
                    : null,
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function prescriptions(Patient $patient, int $limit): Collection
    {
        return $patient->prescriptions()->with('items')->limit($limit)->get()
            ->map(fn ($prescription) => [
                'type' => 'prescriptions',
                'label' => 'Ordonnance',
                'date' => $prescription->issued_on,
                'title' => $prescription->prescription_number,
                'detail' => $prescription->items->pluck('medication_name')->take(3)->implode(', ')
                    ?: 'Aucun médicament',
                'status' => $prescription->statusLabel(),
                'url' => route('dme.prescriptions.show', $prescription),
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function laboratory(Patient $patient, int $limit): Collection
    {
        return $patient->labOrders()->with('items')->limit($limit)->get()
            ->map(fn ($order) => [
                'type' => 'laboratory',
                'label' => 'Laboratoire',
                'date' => $order->requested_at,
                'title' => $order->order_number,
                'detail' => $order->items->pluck('exam_name')->take(3)->implode(', ') ?: 'Aucun examen',
                'status' => $order->statusLabel(),
                'url' => route('dme.laboratory.show', $order),
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function imaging(Patient $patient, int $limit): Collection
    {
        return $patient->imagingOrders()->limit($limit)->get()
            ->map(fn ($order) => [
                'type' => 'imaging',
                'label' => 'Imagerie',
                'date' => $order->requested_at,
                'title' => $order->modalityLabel().($order->body_site ? ' - '.$order->body_site : ''),
                'detail' => $order->order_number,
                'status' => $order->statusLabel(),
                'url' => route('dme.imaging.show', $order),
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function hospitalizations(Patient $patient, int $limit): Collection
    {
        return $patient->hospitalizations()->with('service')->limit($limit)->get()
            ->map(fn ($stay) => [
                'type' => 'hospitalizations',
                'label' => 'Hospitalisation',
                'date' => $stay->admitted_at,
                'title' => $stay->hospitalization_number,
                'detail' => $stay->service?->name ?? ($stay->admission_reason ?: 'Séjour'),
                'status' => $stay->statusLabel(),
                'url' => route('dme.hospitalizations.show', $stay),
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function documents(Patient $patient, int $limit): Collection
    {
        return $patient->documents()->limit($limit)->get()
            ->map(fn ($document) => [
                'type' => 'documents',
                'label' => 'Document',
                'date' => $document->created_at,
                'title' => $document->title,
                'detail' => $document->typeLabel().' · '.$document->humanSize(),
                'status' => null,
                'url' => route('dme.documents.show', $document),
            ]);
    }
}
