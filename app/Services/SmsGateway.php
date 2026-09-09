<?php

namespace App\Services;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Passerelle SMS s'appuyant sur SMSGate (application Android auto-hebergee).
 *
 * Toutes les coordonnees (URL, identifiants) proviennent de config/services.php,
 * donc du fichier .env : aucun identifiant n'est ecrit en dur ici.
 */
class SmsGateway
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly ?string $baseUrl,
        private readonly ?string $login,
        private readonly ?string $password,
        private readonly int $timeout = 10,
        private readonly bool $enabled = true,
    ) {}

    /**
     * Envoie un SMS. N'echoue jamais bruyamment : un SMS non parti ne doit pas
     * annuler un enregistrement ou un renvoi deja valide cote metier.
     *
     * Conserve pour les appels qui ne s'interessent qu'au succes ; la file
     * d'attente, elle, passe par `deliver()` dont elle exploite le detail.
     */
    public function send(string $to, string $text): bool
    {
        return $this->deliver($to, $text)->successful;
    }

    /**
     * Meme envoi que `send()`, mais en rendant compte : identifiant attribue
     * par la passerelle, raison de l'echec, et surtout s'il vaut la peine de
     * reessayer. C'est ce que `SendSmsJob` a besoin de savoir pour distinguer
     * un telephone endormi, qui repondra a la prochaine tentative, d'une
     * passerelle desactivee, qui ne repondra jamais.
     */
    public function deliver(string $to, string $text): SmsSendResult
    {
        $to = $this->normalise($to);

        if ($to === '' || trim($text) === '') {
            return SmsSendResult::rejected('Numero de telephone ou message vide.');
        }

        if (! $this->enabled || ! $this->baseUrl) {
            Log::info('SMS non envoye (passerelle desactivee ou non configuree)', [
                'to' => $to,
            ]);

            return SmsSendResult::rejected('Passerelle SMS desactivee ou non configuree.');
        }

        try {
            $response = $this->http
                ->withBasicAuth((string) $this->login, (string) $this->password)
                ->timeout($this->timeout)
                ->acceptJson()
                ->post(rtrim($this->baseUrl, '/').'/message', [
                    'message' => $text,
                    'phoneNumbers' => [$to],
                ]);

            if ($response->successful()) {
                // SMSGate renvoie l'identifiant qu'il attribue au message. Il
                // atteste de l'acceptation, pas de la remise : voir la note sur
                // le statut « delivered » dans docs/exploitation-demo.md.
                $identifiant = $response->json('id');

                return SmsSendResult::sent(is_scalar($identifiant) ? (string) $identifiant : null);
            }

            Log::warning('Echec d\'envoi SMS', [
                'to' => $to,
                'status' => $response->status(),
            ]);

            $raison = sprintf('La passerelle a repondu %d.', $response->status());

            // Une erreur 4xx traduit une demande que la passerelle refusera
            // toujours (identifiants invalides, numero rejete) : la reessayer
            // ne ferait que retarder le meme constat. On excepte 408 et 429,
            // qui invitent explicitement a recommencer plus tard.
            return $response->clientError() && ! in_array($response->status(), [408, 429], true)
                ? SmsSendResult::rejected($raison)
                : SmsSendResult::failed($raison);
        } catch (Throwable $e) {
            Log::warning('Passerelle SMS injoignable', [
                'to' => $to,
                'message' => $e->getMessage(),
            ]);

            return SmsSendResult::failed('Passerelle injoignable : '.$e->getMessage());
        }
    }

    /**
     * Met le numero au format international attendu par SMSGate.
     * Les numeros maliens saisis a 8 chiffres sont prefixes de l'indicatif pays.
     */
    private function normalise(string $number): string
    {
        $number = trim($number);

        if ($number === '') {
            return '';
        }

        $plus = str_starts_with($number, '+');
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        if ($digits === '') {
            return '';
        }

        if ($plus) {
            return '+'.$digits;
        }

        $countryCode = ltrim((string) config('services.smsgate.country_code', '223'), '+');

        if (str_starts_with($digits, '00')) {
            return '+'.substr($digits, 2);
        }

        if ($countryCode !== '' && str_starts_with($digits, $countryCode) && strlen($digits) > strlen($countryCode)) {
            return '+'.$digits;
        }

        return '+'.$countryCode.$digits;
    }
}
