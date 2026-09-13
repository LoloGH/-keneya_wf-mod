<?php

declare(strict_types=1);

namespace Keneya\Dme\Policies;

use Keneya\Dme\Contracts\DmeUser;
use Keneya\Dme\Models\Patient;
use Illuminate\Database\Eloquent\Model;

/**
 * Accès au dossier patient.
 *
 * En phase 1 tout professionnel disposant de `patients.view` accède aux
 * dossiers de l'établissement, ce qui correspond au fonctionnement d'un
 * DME hospitalier. La restriction fine (patient de son seul service, ou
 * relation de soin établie) est un point d'évolution documenté dans le
 * README : elle ne peut être tranchée sans règle organisationnelle.
 */
class PatientPolicy extends DomainPolicy
{
    protected string $viewPermission = 'patients.view';

    protected string $createPermission = 'patients.create';

    protected string $updatePermission = 'patients.update';

    protected ?string $deletePermission = 'patients.delete';

    /**
     * Un dossier archivé ou décédé reste consultable, mais n'est plus
     * modifiable en dehors du rôle administrateur.
     */
    public function update(DmeUser $user, Model $model): bool
    {
        if (! parent::update($user, $model)) {
            return false;
        }

        if ($model instanceof Patient && $model->status === 'archived') {
            return $user->can('patients.delete');
        }

        return true;
    }

    /**
     * Archiver un dossier : il sort des listes et n'est plus modifiable,
     * mais reste entièrement consultable, et se restaure.
     *
     * C'est l'opération courante, celle d'un dossier qui n'a plus lieu de
     * figurer parmi les patients suivis. Elle ne détruit rien.
     */
    public function archive(DmeUser $user, Patient $patient): bool
    {
        return $patient->status !== 'archived' && $this->allows($user, 'patients.delete');
    }

    public function restore(DmeUser $user, Patient $patient): bool
    {
        return $patient->status === 'archived' && $this->allows($user, 'patients.delete');
    }

    /**
     * Détruire définitivement un dossier et tout son contenu clinique.
     *
     * Permission distincte de l'archivage, et volontairement : archiver est
     * un geste d'organisation, détruire un dossier médical n'en est pas un.
     * Seul le rôle administrateur la reçoit à l'amorçage.
     *
     * Un dossier archivé d'abord : on ne détruit pas un dossier encore actif
     * d'un seul clic. Le passage par l'archive laisse le temps de se
     * raviser, et rend le geste délibéré.
     */
    public function purge(DmeUser $user, Patient $patient): bool
    {
        return $patient->status === 'archived' && $this->allows($user, 'patients.purge');
    }
}
