<?php

namespace App\Observers;

use App\Models\Visitor;
use App\Services\PatientCodeGenerator;

class VisitorObserver
{
    public function __construct(private readonly PatientCodeGenerator $codes) {}

    public function creating(Visitor $visitor): void
    {
        if (blank($visitor->visitor_code)) {
            $visitor->visitor_code = $this->codes->forVisitor();
        }
    }
}
