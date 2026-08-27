<?php

namespace App\Observers;

use App\Models\Visit;
use App\Services\StaffNotifier;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Notifie le personnel de garde qu'un patient entre dans sa file
 * (v3.2.3, point 2).
 *
 * Un observateur plutot qu'un appel dans chaque action : un patient entre dans
 * une file par six chemins — enregistrement, nouvel episode, arrivee sur
 * rendez-vous, sortie de caisse, renvoi envoye, retour d'un renvoi complete.
 * Les enumerer un a un aurait garanti d'en oublier un au prochain chemin
 * ajoute. Ce qui compte n'est pas comment la visite est arrivee la, c'est
 * qu'elle attend maintenant dans ce service.
 *
 * `ShouldHandleEventsAfterCommit` : une transaction annulee ne doit pas laisser
 * derriere elle la notification d'un patient qui n'est jamais entre.
 */
class VisitObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly StaffNotifier $notifier) {}

    public function created(Visit $visit): void
    {
        if ($visit->status !== Visit::STATUS_WAITING) {
            return;
        }

        $this->annonce($visit);
    }

    public function updated(Visit $visit): void
    {
        // Seul un changement de service fait une nouvelle file. Un appel de
        // patient ou une cloture n'en est pas une.
        if (! $visit->wasChanged('service_id') || $visit->status !== Visit::STATUS_WAITING) {
            return;
        }

        $this->annonce($visit);
    }

    private function annonce(Visit $visit): void
    {
        $this->notifier->queueEntry(
            $visit->service_id,
            $visit->patient?->name ?? 'Un patient',
        );
    }
}
