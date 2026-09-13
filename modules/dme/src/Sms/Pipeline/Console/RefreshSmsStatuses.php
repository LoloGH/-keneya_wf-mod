<?php

declare(strict_types=1);

namespace Keneya\Dme\Sms\Pipeline\Console;

use Keneya\Dme\Models\SmsMessage;
use Keneya\Dme\Sms\Pipeline\SmsGatewayManager;
use Keneya\Dme\Sms\Pipeline\SmsService;
use Keneya\Dme\Sms\Pipeline\TracksDeliveryStatus;
use Illuminate\Console\Command;

/**
 * Rafraîchit l'état des SMS encore en transit.
 *
 * SMSGate accuse réception d'un message avant de l'avoir émis : sans ce
 * suivi, l'historique resterait bloqué sur « Accepté par la passerelle »
 * et l'établissement n'aurait aucun moyen de savoir si le patient a
 * réellement été joint.
 *
 * À planifier toutes les cinq minutes (voir routes/console.php).
 */
class RefreshSmsStatuses extends Command
{
    protected $signature = 'keneya:sms:refresh
                            {--limit= : Nombre maximal de messages à interroger}';

    protected $description = 'Interroge la passerelle SMS sur les messages encore en transit';

    public function handle(SmsService $sms, SmsGatewayManager $gateways): int
    {
        if (! config('dme.sms.status_tracking.enabled')) {
            $this->comment('Suivi d\'acheminement désactivé (SMS_STATUS_TRACKING).');

            return self::SUCCESS;
        }

        if (! $gateways->gateway() instanceof TracksDeliveryStatus) {
            $this->comment(
                'La passerelle « '.$gateways->defaultName().' » ne suit pas l\'acheminement : rien à faire.'
            );

            return self::SUCCESS;
        }

        $limit = (int) ($this->option('limit') ?: config('dme.sms.status_tracking.batch_size', 100));
        $maxAge = (int) config('dme.sms.status_tracking.max_age_hours', 48);

        $messages = SmsMessage::query()
            ->inTransit()
            ->where('created_at', '>=', now()->subHours($maxAge))
            ->orderBy('status_checked_at')
            ->limit($limit)
            ->get();

        if ($messages->isEmpty()) {
            $this->info('Aucun message en transit.');

            return self::SUCCESS;
        }

        $changed = 0;

        foreach ($messages as $message) {
            $before = $message->status;
            $sms->refreshStatus($message);

            if ($message->fresh()?->status !== $before) {
                $changed++;
            }
        }

        $this->info("{$messages->count()} message(s) interrogé(s), {$changed} mise(s) à jour.");

        return self::SUCCESS;
    }
}
