<?php

declare(strict_types=1);

namespace Keneya\Dme\Policies;

use Keneya\Dme\Contracts\DmeUser;
use Keneya\Dme\Models\ImagingOrder;

/** Imagerie (§24) : la demande et le compte rendu sont deux droits distincts. */
class ImagingOrderPolicy extends DomainPolicy
{
    protected string $viewPermission = 'imaging.view';

    protected string $createPermission = 'imaging.create';

    protected string $updatePermission = 'imaging.create';

    public function report(DmeUser $user, ImagingOrder $order): bool
    {
        return $this->allows($user, 'imaging.reports.create')
            && $order->status !== 'cancelled';
    }
}
