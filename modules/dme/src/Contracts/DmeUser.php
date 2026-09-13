<?php

declare(strict_types=1);

namespace Keneya\Dme\Contracts;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Ce que le module attend du compte connecté.
 *
 * Le DME partage la table `users` avec son application hôte, et c'est le
 * modèle utilisateur de l'hôte que `Auth::user()` lui remet. Les policies
 * et les écrans du module ne peuvent donc pas typer une classe précise :
 * ils typent ce contrat, que les deux modèles satisfont.
 *
 * Le plus court chemin pour le remplir, côté hôte, est d'ajouter le trait
 * {@see \Keneya\Dme\Models\Concerns\IsDmePractitioner} au modèle `User` de
 * l'application : il fournit l'intégralité des méthodes déclarées ici,
 * ainsi que les relations du dossier médical.
 *
 * Un hôte qui monte le module sans satisfaire ce contrat s'en aperçoit à
 * la première autorisation vérifiée, et non au détour d'un écran : c'est
 * volontaire.
 */
interface DmeUser extends Authenticatable, Authorizable
{
    /**
     * Un compte désactivé conserve son historique mais ne peut plus agir.
     */
    public function isActive(): bool;

    /**
     * De garde à l'instant présent : ce qui conditionne la visibilité des
     * soins programmés sans destinataire nommé.
     */
    public function isOnDuty(): bool;

    /**
     * Nom d'affichage complet, titre professionnel inclus.
     */
    public function displayName(): string;

    /**
     * Initiales, pour les pastilles d'interface.
     */
    public function initials(): string;

    /**
     * Libellé porté dans le journal d'audit.
     */
    public function auditLabel(): string;
}
