<?php

declare(strict_types=1);

namespace Keneya\Dme\Policies;

use Keneya\Dme\Contracts\DmeUser;
use Keneya\Dme\Models\CareOrder;
use Illuminate\Database\Eloquent\Model;

/**
 * Soins programmés.
 *
 * La permission ouvre le domaine ; la portée décide de la ligne. Un
 * infirmier a bien `care_orders.view`, mais cela ne lui donne pas accès
 * aux soins d'un autre service : la règle métier est que le soin ouvert
 * appartient à la garde du service prescripteur.
 */
class CareOrderPolicy extends DomainPolicy
{
    protected string $viewPermission = 'care_orders.view';

    protected string $createPermission = 'care_orders.create';

    protected string $updatePermission = 'care_orders.assign';

    /**
     * Accès à un soin précis : permission, puis portée.
     */
    public function view(DmeUser $user, Model $model): bool
    {
        return $this->allows($user, $this->viewPermission)
            && $this->inScope($user, $model);
    }

    /**
     * Confier ou reprendre un soin. Un soin clos ne se réattribue pas :
     * il faudrait en prescrire un nouveau, ce qui laisse une trace.
     */
    public function assign(DmeUser $user, CareOrder $order): bool
    {
        return $order->isOpen()
            && $this->allows($user, 'care_orders.assign')
            && $this->inScope($user, $order);
    }

    /**
     * Réaliser ou refuser un soin. Réservé à qui le porte réellement :
     * le soignant nommé, ou le personnel de garde du service quand le
     * soin est resté ouvert.
     */
    public function execute(DmeUser $user, CareOrder $order): bool
    {
        if (! $order->isOpen() || ! $this->allows($user, 'care_orders.execute')) {
            return false;
        }

        if ($order->assigned_nurse_id !== null) {
            return $order->assigned_nurse_id === $user->id;
        }

        return $user->isOnDuty()
            && $user->service_id !== null
            && $user->service_id === $order->service_id;
    }

    /**
     * Annuler. Le prescripteur revient sur sa propre demande ;
     * l'administration peut trancher un soin resté ouvert par erreur.
     */
    public function cancel(DmeUser $user, CareOrder $order): bool
    {
        if (! $order->isOpen() || ! $this->allows($user, 'care_orders.cancel')) {
            return false;
        }

        return $order->prescriber_id === $user->id || $user->can('users.manage');
    }

    /** Un soin programmé ne se modifie pas et ne se supprime pas (§30). */
    public function update(DmeUser $user, Model $model): bool
    {
        return false;
    }

    public function delete(DmeUser $user, Model $model): bool
    {
        return false;
    }

    /**
     * Portée de visibilité, miroir exact de CareOrder::scopeVisibleTo.
     */
    private function inScope(DmeUser $user, Model $order): bool
    {
        if ($user->can('users.manage')) {
            return true;
        }

        if ($order->prescriber_id === $user->id || $order->assigned_nurse_id === $user->id) {
            return true;
        }

        return $order->assigned_nurse_id === null
            && $user->isOnDuty()
            && $user->service_id !== null
            && $user->service_id === $order->service_id;
    }
}
