<?php

declare(strict_types=1);

namespace Keneya\Dme\Policies;

use Keneya\Dme\Contracts\DmeUser;
use Keneya\Dme\Models\Consultation;
use Illuminate\Database\Eloquent\Model;

class ConsultationPolicy extends DomainPolicy
{
    protected string $viewPermission = 'consultations.view';

    protected string $createPermission = 'consultations.create';

    protected string $updatePermission = 'consultations.update';

    /**
     * Une consultation terminée n'est plus modifiable : le contenu
     * médical validé doit rester intègre (§40). Une correction passe par
     * une nouvelle consultation ou une note complémentaire.
     */
    public function update(DmeUser $user, Model $model): bool
    {
        return parent::update($user, $model)
            && $model instanceof Consultation
            && $model->isEditable();
    }

    /** Clôture définitive de la consultation. */
    public function complete(DmeUser $user, Consultation $consultation): bool
    {
        return $this->allows($user, 'consultations.update') && $consultation->isEditable();
    }
}
