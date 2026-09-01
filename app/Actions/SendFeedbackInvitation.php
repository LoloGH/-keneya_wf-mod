<?php

namespace App\Actions;

use App\Jobs\SendSmsJob;
use App\Models\Patient;
use App\Models\SmsMessage;
use App\Models\Visitor;

/**
 * Invitation a donner son avis, par SMS (v3.2.8, point 4).
 *
 * Deux destinataires, deux adresses, un meme principe : le lien porte un
 * identifiant long et non devinable, et rien d'autre ne l'ouvre.
 *
 * Le patient est renvoye vers son portail existant plutot que vers un second
 * systeme d'acces : il y trouve deja ses documents, son code a quatre chiffres
 * lui a deja ete remis, et la section « Donner votre avis » s'y ajoute. Un
 * deuxieme mecanisme d'acces aurait double la surface a proteger pour le meme
 * service rendu.
 */
class SendFeedbackInvitation
{
    /** Envoye a la cloture de la visite, sans demande du personnel. */
    public function toPatient(Patient $patient): ?SmsMessage
    {
        return SendSmsJob::dispatch($patient->mobile, sprintf(
            '%s : votre passage est termine. Donnez-nous votre avis ici %s — votre code personnel vous a ete remis a l\'accueil.',
            config('keneya.name'),
            route('portal.show', $patient->portal_token),
        ), $patient);
    }

    /**
     * Envoye quelques heures apres l'enregistrement du visiteur. Aucun code a
     * quatre chiffres : un visiteur n'a pas de dossier medical a proteger, et
     * le contenu de cette page n'est pas medical.
     */
    public function toVisitor(Visitor $visitor): ?SmsMessage
    {
        return SendSmsJob::dispatch($visitor->mobile, sprintf(
            '%s : merci de votre visite. Votre avis nous aide a mieux accueillir : %s',
            config('keneya.name'),
            route('feedback.visitor', $visitor->feedback_token),
        ), $visitor);
    }
}
