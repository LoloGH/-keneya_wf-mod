<?php

declare(strict_types=1);

namespace Keneya\Dme\Sms\Pipeline;

use Keneya\Dme\Models\SmsMessage;
use Keneya\Dme\Sms\Pipeline\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envoi asynchrone d'un SMS (§53).
 *
 * Le job ne transporte que l'identifiant du message : le contenu est relu
 * en base au moment de l'exécution, ce qui évite de sérialiser des données
 * personnelles dans la file d'attente.
 *
 * Les tentatives et le délai de réessai proviennent de config/sms.php.
 */
class SendSmsMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $smsMessageId)
    {
    }

    public function tries(): int
    {
        return (int) config('dme.sms.retry.max_attempts', 3);
    }

    /**
     * Délai croissant entre les tentatives.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        $delay = (int) config('dme.sms.retry.delay_seconds', 60);

        return [$delay, $delay * 2, $delay * 4];
    }

    public function handle(SmsService $sms): void
    {
        $message = SmsMessage::find($this->smsMessageId);

        if ($message === null || $message->status === 'cancelled') {
            return;
        }

        $result = $sms->deliver($message);

        // Un échec relance le job tant qu'il reste des tentatives ; le
        // statut « failed » déjà écrit rend l'incident visible dans
        // l'historique sans attendre l'épuisement des essais.
        if (! $result->successful) {
            throw new \RuntimeException($result->error ?? 'Échec inconnu de la passerelle SMS.');
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('Envoi SMS définitivement en échec', [
            'sms_message_id' => $this->smsMessageId,
            'error' => $exception->getMessage(),
        ]);
    }
}
