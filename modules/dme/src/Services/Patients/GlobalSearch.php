<?php

declare(strict_types=1);

namespace Keneya\Dme\Services\Patients;

use Keneya\Dme\Contracts\DmeUser;
use Keneya\Dme\Models\Appointment;
use Keneya\Dme\Models\Consultation;
use Keneya\Dme\Models\ImagingOrder;
use Keneya\Dme\Models\LabOrder;
use Keneya\Dme\Models\MedicalDocument;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\Prescription;
use Illuminate\Support\Collection;

/**
 * Recherche globale depuis la barre supérieure (§34).
 *
 * Les résultats sont groupés par catégorie et filtrés par les permissions
 * de l'utilisateur : une recherche ne doit jamais révéler l'existence
 * d'un enregistrement que l'utilisateur n'a pas le droit de consulter.
 */
class GlobalSearch
{
    private const PER_CATEGORY = 5;

    /**
     * @return Collection<string, Collection<int, array{title: string, subtitle: string, url: string}>>
     */
    public function search(string $term, DmeUser $user): Collection
    {
        $term = trim($term);
        $results = collect();

        if (mb_strlen($term) < 2) {
            return $results;
        }

        $like = '%'.$term.'%';

        if ($user->can('patients.view')) {
            $results->put('Patients', Patient::search($term)
                ->limit(self::PER_CATEGORY)->get()
                ->map(fn (Patient $patient) => [
                    'title' => $patient->fullName(),
                    'subtitle' => $patient->patient_number.' · '.$patient->ageLabel(),
                    'url' => route('dme.patients.show', $patient),
                ]));
        }

        if ($user->can('consultations.view')) {
            $results->put('Consultations', Consultation::with('patient')
                ->where('consultation_number', 'like', $like)
                ->orWhere('reason', 'like', $like)
                ->limit(self::PER_CATEGORY)->get()
                ->map(fn (Consultation $consultation) => [
                    'title' => $consultation->consultation_number,
                    'subtitle' => $consultation->patient->fullName().' · '.$consultation->started_at->format('d/m/Y'),
                    'url' => route('dme.consultations.show', $consultation),
                ]));
        }

        if ($user->can('prescriptions.view')) {
            $results->put('Ordonnances', Prescription::with('patient')
                ->where('prescription_number', 'like', $like)
                ->limit(self::PER_CATEGORY)->get()
                ->map(fn (Prescription $prescription) => [
                    'title' => $prescription->prescription_number,
                    'subtitle' => $prescription->patient->fullName().' · '.$prescription->statusLabel(),
                    'url' => route('dme.prescriptions.show', $prescription),
                ]));
        }

        if ($user->can('laboratory.view')) {
            $results->put('Laboratoire', LabOrder::with('patient')
                ->where('order_number', 'like', $like)
                ->limit(self::PER_CATEGORY)->get()
                ->map(fn (LabOrder $order) => [
                    'title' => $order->order_number,
                    'subtitle' => $order->patient->fullName().' · '.$order->statusLabel(),
                    'url' => route('dme.laboratory.show', $order),
                ]));
        }

        if ($user->can('imaging.view')) {
            $results->put('Imagerie', ImagingOrder::with('patient')
                ->where('order_number', 'like', $like)
                ->limit(self::PER_CATEGORY)->get()
                ->map(fn (ImagingOrder $order) => [
                    'title' => $order->order_number.' - '.$order->modalityLabel(),
                    'subtitle' => $order->patient->fullName(),
                    'url' => route('dme.imaging.show', $order),
                ]));
        }

        if ($user->can('documents.view')) {
            $results->put('Documents', MedicalDocument::with('patient')
                ->where('title', 'like', $like)
                ->orWhere('document_number', 'like', $like)
                ->limit(self::PER_CATEGORY)->get()
                ->map(fn (MedicalDocument $document) => [
                    'title' => $document->title,
                    'subtitle' => $document->patient->fullName().' · '.$document->typeLabel(),
                    'url' => route('dme.documents.show', $document),
                ]));
        }

        if ($user->can('appointments.view')) {
            $results->put('Rendez-vous', Appointment::with('patient')
                ->where('appointment_number', 'like', $like)
                ->limit(self::PER_CATEGORY)->get()
                ->map(fn (Appointment $appointment) => [
                    'title' => $appointment->appointment_number,
                    'subtitle' => $appointment->patient->fullName().' · '.$appointment->scheduled_for->format('d/m/Y H:i'),
                    'url' => route('dme.appointments.show', $appointment),
                ]));
        }

        return $results->filter(fn (Collection $group) => $group->isNotEmpty());
    }
}
