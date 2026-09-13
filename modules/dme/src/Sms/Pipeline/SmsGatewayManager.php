<?php

declare(strict_types=1);

namespace Keneya\Dme\Sms\Pipeline;

use Keneya\Dme\Sms\Pipeline\Gateways\ArrayGateway;
use Keneya\Dme\Sms\Pipeline\Gateways\LogGateway;
use Keneya\Dme\Sms\Pipeline\Gateways\SmsGateGateway;
use InvalidArgumentException;

/**
 * Résout la passerelle SMS active à partir de config/sms.php.
 */
class SmsGatewayManager
{
    /** @var array<string, SmsGateway> */
    private array $resolved = [];

    public function gateway(?string $name = null): SmsGateway
    {
        $name ??= $this->defaultName();

        return $this->resolved[$name] ??= $this->resolve($name);
    }

    public function defaultName(): string
    {
        return (string) (config('dme.sms.gateway') ?: 'log');
    }

    /**
     * Indique si la passerelle active émet réellement des SMS.
     *
     * L'interface s'appuie dessus pour ne jamais laisser croire qu'un
     * message a été transmis à un opérateur alors qu'il n'a été que
     * journalisé.
     */
    public function isSimulated(?string $name = null): bool
    {
        return in_array($name ?? $this->defaultName(), ['log', 'array'], true);
    }

    private function resolve(string $name): SmsGateway
    {
        $config = config("dme.sms.gateways.{$name}");

        if (! is_array($config)) {
            throw new InvalidArgumentException("Passerelle SMS « {$name} » non configurée.");
        }

        return match ($config['driver'] ?? $name) {
            'smsgate' => new SmsGateGateway($config),
            'log' => new LogGateway($config),
            'array' => new ArrayGateway(),
            default => throw new InvalidArgumentException(
                "Pilote de passerelle SMS « {$config['driver']} » inconnu."
            ),
        };
    }

    /**
     * Enregistre une passerelle personnalisée (tests, opérateur local).
     */
    public function extend(string $name, SmsGateway $gateway): void
    {
        $this->resolved[$name] = $gateway;
    }
}
