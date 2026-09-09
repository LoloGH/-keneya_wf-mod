<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PatientHistory;
use App\Models\User;
use App\Models\Visit;
use App\Models\Visitor;

/**
 * A qui se rapporte la note « personnel » d'un retour (v3.2.8, point 4).
 *
 * Le principe tient en une phrase : rester nul plutot que deviner. Une note
 * attribuee au mauvais agent est pire qu'une note sans destinataire : elle
 * accuse quelqu'un a sa place.
 */
class FeedbackAttribution
{
    /**
     * Pour un patient : le dernier medecin l'ayant consulte sur la visite
     * concernee, tel que le dossier l'a enregistre au moment de l'envoi.
     */
    public function forVisit(?Visit $visit): ?User
    {
        if (! $visit) {
            return null;
        }

        $ligne = PatientHistory::query()
            ->with(['doctor.user', 'staffMember.user'])
            ->where('visit_id', $visit->getKey())
            ->where('type', PatientHistory::TYPE_CONSULTATION)
            ->orderByDesc('id')
            ->first();

        return $ligne?->doctor?->user ?? $ligne?->staffMember?->user;
    }

    /** Le dernier passage du patient, a defaut d'une visite designee. */
    public function forPatient(Patient $patient): ?User
    {
        return $this->forVisit($patient->visits()->orderByDesc('opened_at')->first());
    }

    /**
     * Pour un visiteur : l'agent d'accueil qui l'a recu. C'est la seule
     * personne qu'il ait rencontree, et donc la seule qu'il puisse noter.
     */
    public function forVisitor(Visitor $visitor): ?User
    {
        return $visitor->registeredBy;
    }
}
