<?php

declare(strict_types=1);

namespace Keneya\Dme\Policies;

use Keneya\Dme\Contracts\DmeUser;
use Keneya\Dme\Models\SmsMessage;

/** Service SMS (§35) : consultation de l'historique et envoi manuel. */
class SmsMessagePolicy
{
    public function viewAny(DmeUser $user): bool
    {
        return $user->isActive() && $user->can('sms.view');
    }

    public function view(DmeUser $user, SmsMessage $message): bool
    {
        return $this->viewAny($user);
    }

    public function create(DmeUser $user): bool
    {
        return $user->isActive() && $user->can('sms.send');
    }

    public function retry(DmeUser $user, SmsMessage $message): bool
    {
        return $this->create($user) && $message->isRetryable();
    }
}
