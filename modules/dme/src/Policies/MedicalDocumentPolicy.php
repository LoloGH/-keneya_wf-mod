<?php

declare(strict_types=1);

namespace Keneya\Dme\Policies;

use Keneya\Dme\Contracts\DmeUser;
use Keneya\Dme\Models\MedicalDocument;
use Illuminate\Database\Eloquent\Model;

/**
 * Documents médicaux (§28, §42).
 *
 * Le téléchargement est une permission distincte de la consultation :
 * un utilisateur peut voir qu'un document existe sans pouvoir extraire
 * le fichier. Toute décision est prise ici, avant que le fichier ne soit
 * lu sur le disque privé.
 */
class MedicalDocumentPolicy extends DomainPolicy
{
    protected string $viewPermission = 'documents.view';

    protected string $createPermission = 'documents.upload';

    protected string $updatePermission = 'documents.upload';

    public function download(DmeUser $user, MedicalDocument $document): bool
    {
        return $this->allows($user, 'documents.download');
    }

    /** Un document archivé n'est plus remplaçable. */
    public function update(DmeUser $user, Model $model): bool
    {
        return parent::update($user, $model)
            && $model instanceof MedicalDocument
            && $model->status !== 'archived';
    }

    /** Seul un administrateur peut retirer un document du dossier. */
    public function delete(DmeUser $user, Model $model): bool
    {
        return $this->allows($user, 'settings.manage');
    }
}
