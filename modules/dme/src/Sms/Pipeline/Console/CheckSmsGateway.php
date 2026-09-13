<?php

declare(strict_types=1);

namespace Keneya\Dme\Sms\Pipeline\Console;

use Keneya\Dme\Sms\Pipeline\Gateways\SmsGateGateway;
use Keneya\Dme\Sms\Pipeline\SmsGatewayManager;
use Illuminate\Console\Command;

/**
 * Diagnostic de la passerelle SMS.
 *
 * Vérifie la configuration et la joignabilité sans émettre de SMS, et
 * sans jamais afficher d'identifiant. Destinée à l'exploitation, elle
 * répond à la question « pourquoi les SMS ne partent-ils pas ? ».
 */
class CheckSmsGateway extends Command
{
    protected $signature = 'keneya:sms:check';

    protected $description = 'Vérifie la configuration et la joignabilité de la passerelle SMS';

    public function handle(SmsGatewayManager $gateways): int
    {
        $name = $gateways->defaultName();
        $gateway = $gateways->gateway();

        $this->line('Passerelle active : <info>'.$name.'</info>');

        if ($gateways->isSimulated($name)) {
            $this->warn(
                'Cette passerelle est une simulation : aucun SMS réel n\'est émis. '
                .'Définissez SMS_GATEWAY=smsgate pour un envoi réel.'
            );

            return self::SUCCESS;
        }

        if (! $gateway instanceof SmsGateGateway) {
            $this->comment('Aucun diagnostic disponible pour cette passerelle.');

            return self::SUCCESS;
        }

        // Les valeurs ne sont jamais affichées, seulement leur présence.
        $this->line('Endpoint  : '.config('dme.sms.gateways.smsgate.base_url'));
        $this->line('Identifiants : '.(
            filled(config('dme.sms.gateways.smsgate.username')) || filled(config('dme.sms.gateways.smsgate.token'))
                ? '<info>présents</info>'
                : '<error>absents</error>'
        ));

        if ($gateway->healthy()) {
            $this->info('SMSGate répond : la passerelle est joignable.');

            return self::SUCCESS;
        }

        $this->error(
            'SMSGate est injoignable ou refuse les identifiants. '
            .'Vérifiez SMSGATE_BASE_URL, SMSGATE_USERNAME et SMSGATE_PASSWORD.'
        );

        return self::FAILURE;
    }
}
