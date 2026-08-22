<?php

namespace App\Observers;

use App\Models\PatientHistory;
use RuntimeException;

/**
 * Garde-fou du caractere append-only du journal : toute tentative de mise a jour
 * ou de suppression d'une ligne d'historique leve une exception.
 */
class PatientHistoryObserver
{
    public function updating(PatientHistory $history): void
    {
        throw new RuntimeException("L'historique patient est append-only : une ligne deja enregistree ne peut pas etre modifiee.");
    }

    public function deleting(PatientHistory $history): void
    {
        throw new RuntimeException("L'historique patient est append-only : une ligne deja enregistree ne peut pas etre supprimee.");
    }
}
