<?php

namespace App\Actions;

use App\Jobs\SendSmsJob;
use App\Models\Patient;
use App\Models\SmsMessage;
use App\Support\Audit;
use InvalidArgumentException;

/**
 * Envoi du lien « mes documents » par SMS (v3.2, point 7).
 *
 * Sur demande explicite depuis l'accueil ou le service — jamais automatique a
 * chaque evenement : le patient n'a pas a recevoir un SMS a chaque ligne
 * ajoutee a son dossier.
 */
class SendPortalLink
{
    /**
     * Rend la trace mise en file, et non plus un booleen « parti / pas parti » :
     * depuis la v3.2.8 l'envoi reel a lieu en arriere-plan, l'appelant n'a donc
     * plus a l'attendre pour rendre la main. Le sort du message se lit dans la
     * section « SMS » de l'administration.
     */
    public function execute(Patient $patient): SmsMessage
    {
        if (blank($patient->mobile)) {
            throw new InvalidArgumentException("Ce patient n'a pas de numero de telephone.");
        }

        $message = SendSmsJob::dispatch($patient->mobile, sprintf(
            '%s : consultez vos documents et rendez-vous ici %s — votre code personnel vous a ete remis a l\'accueil.',
            config('keneya.name'),
            route('portal.show', $patient->portal_token),
        ), $patient);

        Audit::log(
            Audit::EVENT_PORTAL_LINK_SENT,
            sprintf('Lien de documents envoye a %s.', $patient->patient_code),
            $patient,
        );

        return $message;
    }
}
