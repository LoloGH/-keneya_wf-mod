<?php

declare(strict_types=1);

namespace Keneya\Dme\Policies;

class AppointmentPolicy extends DomainPolicy
{
    protected string $viewPermission = 'appointments.view';

    protected string $createPermission = 'appointments.manage';

    protected string $updatePermission = 'appointments.manage';

    protected ?string $deletePermission = 'appointments.manage';
}
