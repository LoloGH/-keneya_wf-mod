<?php

namespace App\Actions;

use App\Models\Patient;
use App\Services\SmsGateway;
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
    public function __construct(private readonly SmsGateway $sms) {}

    public function execute(Patient $patient): bool
    {
        if (blank($patient->mobile)) {
            throw new InvalidArgumentException("Ce patient n'a pas de numero de telephone.");
        }

        $envoye = $this->sms->send($patient->mobile, sprintf(
            '%s : consultez vos documents et rendez-vous ici %s — votre code personnel vous a ete remis a l\'accueil.',
            config('keneya.name'),
            route('portal.show', $patient->portal_token),
        ));

        Audit::log(
            Audit::EVENT_PORTAL_LINK_SENT,
            sprintf('Lien de documents envoye a %s.', $patient->patient_code),
            $patient,
        );

        return $envoye;
    }
}
