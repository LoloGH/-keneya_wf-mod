<?php

declare(strict_types=1);

namespace Keneya\Dme\Policies;

class DiagnosisPolicy extends DomainPolicy
{
    protected string $viewPermission = 'diagnoses.view';

    protected string $createPermission = 'diagnoses.create';

    protected string $updatePermission = 'diagnoses.create';
}
