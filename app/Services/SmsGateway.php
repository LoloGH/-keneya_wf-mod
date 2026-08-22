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
     */
    public function send(string $to, string $text): bool
    {
        $to = $this->normalise($to);

        if ($to === '' || trim($text) === '') {
            return false;
        }

        if (! $this->enabled || ! $this->baseUrl) {
            Log::info('SMS non envoye (passerelle desactivee ou non configuree)', [
                'to' => $to,
            ]);

            return false;
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
                return true;
            }

            Log::warning('Echec d\'envoi SMS', [
                'to' => $to,
                'status' => $response->status(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Passerelle SMS injoignable', [
                'to' => $to,
                'message' => $e->getMessage(),
            ]);
        }

        return false;
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
