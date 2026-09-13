<?php

declare(strict_types=1);

namespace Keneya\Dme\Contracts;

/**
 * Contrat d'envoi de SMS du module DME.
 *
 * C'est la seule dépendance SMS autorisée dans le code métier du module :
 * ni le service de notifications, ni les contrôleurs, ni les modèles ne
 * connaissent l'implémentation qui envoie réellement le message.
 *
 * Le module fournit deux implémentations de repli, utiles tant qu'il
 * fonctionne seul :
 *
 *   - {@see \Keneya\Dme\Sms\LogSmsDispatcher} : journalise, n'émet rien ;
 *   - {@see \Keneya\Dme\Sms\Pipeline\QueuedSmsDispatcher} : file d'attente
 *     interne du module (persistance, passerelle, historique).
 *
 * Monté dans Keneya Workflow, l'hôte liera sa propre implémentation à ce
 * contrat dans son conteneur de services ; le module n'aura rien à changer.
 *
 * Le paramètre `$context` est une chaîne libre décrivant l'événement à
 * l'origine du message. Le module la produit avec {@see \Keneya\Dme\Sms\SmsContext},
 * qui en fixe le format ; une implémentation hôte est libre de l'ignorer,
 * de la journaliser telle quelle ou de la décoder.
 */
interface SmsDispatcherContract
{
    /**
     * Remet un message à l'infrastructure d'envoi.
     *
     * L'implémentation ne doit jamais laisser échapper l'échec d'un envoi
     * jusqu'à l'appelant : un SMS manqué ne doit pas interrompre un acte
     * médical. Seule une donnée d'entrée inexploitable (numéro invalide)
     * justifie une exception.
     */
    public function dispatch(string $to, string $message, ?string $context = null): void;
}
