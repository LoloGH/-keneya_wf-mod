<?php

declare(strict_types=1);

namespace Keneya\Dme\Sms\Pipeline\Gateways;

use Keneya\Dme\Sms\Pipeline\SmsGateway;
use Keneya\Dme\Sms\Pipeline\SmsResult;

/**
 * Passerelle inerte : accepte tout et n'émet rien.
 *
 * Utilisée par la suite de tests pour vérifier le cycle de vie complet
 * d'un message (mise en file, tentative, statut, historique) sans
 * dépendre d'un service externe.
 */
class ArrayGateway implements SmsGateway
{
    public function send(string $recipient, string $body, ?string $sender = null): SmsResult
    {
        return SmsResult::success($this->name(), 'array-'.md5($recipient.$body));
    }

    public function name(): string
    {
        return 'array';
    }
}
