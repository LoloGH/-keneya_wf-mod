<?php

declare(strict_types=1);

namespace Keneya\Dme\Sms\Pipeline\Gateways;

use Keneya\Dme\Sms\Pipeline\SmsGateway;
use Keneya\Dme\Sms\Pipeline\SmsResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Passerelle de développement et de démonstration : aucun SMS réel n'est
 * émis, le message est écrit dans les logs applicatifs.
 *
 * C'est la passerelle par défaut (config/sms.php) afin qu'une
 * installation de démonstration ne puisse jamais contacter de vraies
 * personnes.
 */
class LogGateway implements SmsGateway
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config = [])
    {
    }

    public function send(string $recipient, string $body, ?string $sender = null): SmsResult
    {
        $messageId = 'log-'.Str::uuid()->toString();

        Log::channel($this->config['channel'] ?? config('logging.default'))->info('SMS (simulation)', [
            'gateway' => $this->name(),
            'recipient' => $recipient,
            'sender' => $sender,
            'body' => $body,
            'message_id' => $messageId,
        ]);

        return SmsResult::success($this->name(), $messageId, ['simulated' => true]);
    }

    public function name(): string
    {
        return 'log';
    }
}
