<?php

declare(strict_types=1);

namespace Keneya\Dme\Sms\Pipeline\Gateways;

use Keneya\Dme\Sms\Pipeline\SmsGateway;
use Keneya\Dme\Sms\Pipeline\SmsResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Passerelle HTTP générique : modèle d'intégration d'un opérateur réel.
 *
 * L'endpoint et le jeton proviennent exclusivement de la configuration :
 * aucun secret n'est présent dans le dépôt (§66). Le service n'est actif
 * que si SMS_DRIVER=http et que l'endpoint est renseigné.
 */
class HttpGateway implements SmsGateway
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config = [])
    {
    }

    public function send(string $recipient, string $body, ?string $sender = null): SmsResult
    {
        $endpoint = $this->config['endpoint'] ?? null;

        if (! is_string($endpoint) || $endpoint === '') {
            return SmsResult::failure(
                $this->name(),
                'Aucun endpoint SMS configuré (SMS_HTTP_ENDPOINT).'
            );
        }

        try {
            $response = Http::timeout((int) ($this->config['timeout'] ?? 10))
                ->withToken((string) ($this->config['token'] ?? ''))
                ->asJson()
                ->post($endpoint, [
                    'to' => $recipient,
                    'from' => $sender,
                    'text' => $body,
                ]);

            if ($response->failed()) {
                return SmsResult::failure(
                    $this->name(),
                    'Réponse '.$response->status().' de la passerelle.',
                    ['status' => $response->status()],
                );
            }

            $payload = $response->json();

            return SmsResult::success(
                $this->name(),
                is_array($payload) ? ($payload['id'] ?? $payload['message_id'] ?? null) : null,
                is_array($payload) ? $payload : [],
            );
        } catch (Throwable $exception) {
            return SmsResult::failure($this->name(), $exception->getMessage());
        }
    }

    public function name(): string
    {
        return 'http';
    }
}
