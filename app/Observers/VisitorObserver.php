<?php

namespace App\Observers;

use App\Models\Visitor;
use App\Services\PatientCodeGenerator;
use App\Services\StaffNotifier;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class VisitorObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly PatientCodeGenerator $codes,
        private readonly StaffNotifier $notifier,
    ) {}

    public function creating(Visitor $visitor): void
    {
        if (blank($visitor->visitor_code)) {
            $visitor->visitor_code = $this->codes->forVisitor();
        }
    }

    /**
     * Un visiteur entre dans la meme file qu'un patient : le personnel de
     * garde doit l'apprendre de la meme facon (v3.2.3, point 2).
     */
    public function created(Visitor $visitor): void
    {
        $this->notifier->queueEntry($visitor->service_id, $visitor->name ?: 'Un visiteur');
    }
}
