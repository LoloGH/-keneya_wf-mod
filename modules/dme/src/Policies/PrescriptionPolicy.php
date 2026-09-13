<?php

declare(strict_types=1);

namespace Keneya\Dme\Policies;

use Keneya\Dme\Contracts\DmeUser;
use Keneya\Dme\Models\Prescription;
use Illuminate\Database\Eloquent\Model;

/**
 * Ordonnances (§22).
 *
 * Trois actes distincts et distinctement autorisés : créer (médecin),
 * valider (médecin) et délivrer (pharmacien). Un pharmacien ne peut donc
 * jamais modifier le contenu d'une prescription.
 */
class PrescriptionPolicy extends DomainPolicy
{
    protected string $viewPermission = 'prescriptions.view';

    protected string $createPermission = 'prescriptions.create';

    protected string $updatePermission = 'prescriptions.create';

    /** Une ordonnance n'est modifiable qu'à l'état de brouillon. */
    public function update(DmeUser $user, Model $model): bool
    {
        return parent::update($user, $model)
            && $model instanceof Prescription
            && $model->isEditable();
    }

    public function validate(DmeUser $user, Prescription $prescription): bool
    {
        return $this->allows($user, 'prescriptions.validate')
            && $prescription->status === 'draft';
    }

    public function dispense(DmeUser $user, Prescription $prescription): bool
    {
        return $this->allows($user, 'prescriptions.dispense')
            && $prescription->status === 'validated';
    }

    /** L'impression / le PDF suit le droit de lecture. */
    public function print(DmeUser $user, Prescription $prescription): bool
    {
        return $this->allows($user, 'prescriptions.view');
    }
}
