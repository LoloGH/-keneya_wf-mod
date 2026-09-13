<?php

declare(strict_types=1);

namespace Keneya\Dme\Sms\Pipeline\Gateways;

use Keneya\Dme\Sms\Pipeline\SmsGateway;
use Keneya\Dme\Sms\Pipeline\SmsResult;
use Keneya\Dme\Sms\Pipeline\TracksDeliveryStatus;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Passerelle SMSGate : SMS Gateway for Android™ (sms-gate.app).
 *
 * Passerelle de production du projet. Elle expose une API REST identique
 * en mode cloud (api.sms-gate.app) et en mode local (appareil Android
 * joignable sur le réseau), ce qui permet d'exploiter l'application dans
 * un établissement sans dépendance à un service tiers payant.
 *
 * Contrat utilisé :
 *   POST   {base}/messages        -> transmet un message
 *   GET    {base}/messages/{id}   -> état d'acheminement
 *   GET    {base}/health          -> disponibilité
 *
 * Authentification HTTP Basic (identifiant + mot de passe) ou Bearer
 * lorsqu'un jeton seul est fourni.
 *
 * Point important : la réponse d'un envoi vaut **accusé de prise en
 * charge**, pas confirmation d'émission. SMSGate répond « Pending » tant
 * que l'appareil n'a pas récupéré le message. L'application ne présente
 * donc jamais un message comme envoyé sur la seule foi de cette réponse ;
 * c'est le suivi d'acheminement qui fait foi.
 */
class SmsGateGateway implements SmsGateway, TracksDeliveryStatus
{
    /**
     * Correspondance entre les états SMSGate et les états du service.
     *
     * @var array<string, string>
     */
    private const STATES = [
        'Pending' => SmsResult::STATE_ACCEPTED,
        'Processed' => SmsResult::STATE_ACCEPTED,
        'Sent' => SmsResult::STATE_SENT,
        'Delivered' => SmsResult::STATE_DELIVERED,
        'Failed' => SmsResult::STATE_FAILED,
        'Cancelled' => SmsResult::STATE_CANCELLED,
        'Cancelling' => SmsResult::STATE_CANCELLED,
    ];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config = [])
    {
    }

    public function name(): string
    {
        return 'smsgate';
    }

    public function send(string $recipient, string $body, ?string $sender = null): SmsResult
    {
        $error = $this->configurationError();

        if ($error !== null) {
            return SmsResult::failure($this->name(), $error);
        }

        $payload = array_filter([
            'message' => $body,
            'phoneNumbers' => [$recipient],
            'withDeliveryReport' => (bool) ($this->config['with_delivery_report'] ?? true),
            'simNumber' => $this->config['sim_number'] ?? null,
            'ttl' => $this->config['ttl'] ?? null,
        ], static fn ($value) => $value !== null);

        try {
            $response = $this->request()->post($this->url('/messages'), $payload);
        } catch (Throwable $exception) {
            // Panne réseau ou TLS : échec transitoire, le job réessaiera.
            return SmsResult::failure($this->name(), $this->sanitize($exception->getMessage()));
        }

        if ($response->failed()) {
            return SmsResult::failure(
                $this->name(),
                $this->errorFrom($response->status(), $response->json(), $response->body()),
                ['status' => $response->status()],
            );
        }

        $data = (array) $response->json();

        return $this->resultFrom($data);
    }

    public function status(string $gatewayMessageId): SmsResult
    {
        $error = $this->configurationError();

        if ($error !== null) {
            return SmsResult::failure($this->name(), $error);
        }

        try {
            $response = $this->request()->get($this->url('/messages/'.rawurlencode($gatewayMessageId)));
        } catch (Throwable $exception) {
            return SmsResult::failure($this->name(), $this->sanitize($exception->getMessage()));
        }

        if ($response->failed()) {
            return SmsResult::failure(
                $this->name(),
                $this->errorFrom($response->status(), $response->json(), $response->body()),
                ['status' => $response->status()],
            );
        }

        return $this->resultFrom((array) $response->json());
    }

    /**
     * Disponibilité de la passerelle : exploitée par la commande de
     * diagnostic, jamais dans un flux médical.
     */
    public function healthy(): bool
    {
        if ($this->configurationError() !== null) {
            return false;
        }

        try {
            return $this->request()->get($this->url('/health'))->successful();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Construit un SmsResult à partir d'une réponse SMSGate.
     *
     * L'état retenu est celui du destinataire lorsqu'il est présent : il
     * est plus précis que l'état global du message, qui agrège plusieurs
     * numéros. Ici un message ne vise qu'un destinataire.
     *
     * @param  array<string, mixed>  $data
     */
    private function resultFrom(array $data): SmsResult
    {
        $messageId = isset($data['id']) ? (string) $data['id'] : null;

        $recipient = $data['recipients'][0] ?? null;
        $rawState = (string) ($recipient['state'] ?? $data['state'] ?? 'Pending');
        $state = self::STATES[$rawState] ?? SmsResult::STATE_ACCEPTED;

        $response = [
            'id' => $messageId,
            'state' => $rawState,
            'recipients' => $data['recipients'] ?? [],
        ];

        if ($state === SmsResult::STATE_FAILED) {
            return new SmsResult(
                successful: false,
                gateway: $this->name(),
                state: SmsResult::STATE_FAILED,
                messageId: $messageId,
                error: $this->sanitize((string) ($recipient['error'] ?? 'Échec signalé par la passerelle SMSGate.')),
                response: $response,
            );
        }

        if ($state === SmsResult::STATE_CANCELLED) {
            return SmsResult::cancelled($this->name(), $messageId, $response);
        }

        return new SmsResult(
            successful: true,
            gateway: $this->name(),
            state: $state,
            messageId: $messageId,
            error: null,
            response: $response,
        );
    }

    /**
     * Client HTTP préconfiguré : authentification, délai, TLS.
     */
    private function request(): PendingRequest
    {
        $request = Http::timeout((int) ($this->config['timeout'] ?? 15))
            ->acceptJson()
            ->asJson()
            ->withUserAgent('keneya-dme/'.config('dme.version', '0.1.0'));

        $username = $this->config['username'] ?? null;
        $password = $this->config['password'] ?? null;
        $token = $this->config['token'] ?? null;

        if (filled($username)) {
            $request = $request->withBasicAuth((string) $username, (string) $password);
        } elseif (filled($token)) {
            $request = $request->withToken((string) $token);
        }

        // Le mode local expose souvent un certificat auto-signé sur le
        // réseau de l'établissement ; la vérification reste active par
        // défaut et ne se désactive que délibérément.
        if (($this->config['verify_tls'] ?? true) === false) {
            $request = $request->withoutVerifying();
        }

        return $request;
    }

    private function url(string $path): string
    {
        return rtrim((string) ($this->config['base_url'] ?? ''), '/').$path;
    }

    /**
     * Vérifie que la passerelle est exploitable avant tout appel réseau.
     */
    private function configurationError(): ?string
    {
        if (blank($this->config['base_url'] ?? null)) {
            return 'SMSGate n\'est pas configuré : SMSGATE_BASE_URL est absent.';
        }

        if (blank($this->config['username'] ?? null) && blank($this->config['token'] ?? null)) {
            return 'SMSGate n\'est pas configuré : renseignez SMSGATE_USERNAME/SMSGATE_PASSWORD ou SMSGATE_TOKEN.';
        }

        return null;
    }

    /**
     * Message d'erreur lisible construit à partir de la réponse HTTP.
     *
     * @param  mixed  $json
     */
    private function errorFrom(int $status, $json, string $body): string
    {
        $message = is_array($json)
            ? ($json['message'] ?? $json['error'] ?? null)
            : null;

        $message ??= Str::limit(trim($body), 200) ?: 'réponse sans détail';

        return match (true) {
            $status === 401, $status === 403 => 'SMSGate a refusé les identifiants (HTTP '.$status.').',
            $status === 404 => 'Ressource SMSGate introuvable (HTTP 404) : vérifiez SMSGATE_BASE_URL.',
            $status >= 500 => 'SMSGate est indisponible (HTTP '.$status.') : '.$this->sanitize((string) $message),
            default => 'SMSGate a rejeté la requête (HTTP '.$status.') : '.$this->sanitize((string) $message),
        };
    }

    /**
     * Retire toute trace de secret d'un message destiné aux journaux ou
     * à l'interface : une exception réseau peut contenir l'URL complète,
     * et donc des identifiants s'ils y figurent.
     */
    private function sanitize(string $message): string
    {
        foreach ([$this->config['password'] ?? null, $this->config['token'] ?? null] as $secret) {
            if (filled($secret)) {
                $message = str_replace((string) $secret, '***', $message);
            }
        }

        // Neutralise une éventuelle forme https://user:pass@hote
        $message = preg_replace('#(https?://)[^/@\s:]+:[^/@\s]+@#i', '$1***@', $message) ?? $message;

        return Str::limit($message, 500);
    }
}
