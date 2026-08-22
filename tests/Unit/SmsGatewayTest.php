<?php

namespace Tests\Unit;

use App\Services\SmsGateway;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Tests\TestCase;

class SmsGatewayTest extends TestCase
{
    public function test_l_envoi_appelle_l_api_smsgate_avec_les_identifiants_configures(): void
    {
        $http = new HttpFactory;
        $http->fake(['*' => $http->response(['id' => 'abc'], 202)]);

        $gateway = new SmsGateway($http, 'https://sms.local:8080', 'utilisateur', 'secret');

        $this->assertTrue($gateway->send('76445566', 'Bonjour'));

        $http->assertSent(function (Request $request): bool {
            return $request->url() === 'https://sms.local:8080/message'
                && $request['message'] === 'Bonjour'
                && $request['phoneNumbers'] === ['+22376445566']
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('utilisateur:secret'));
        });
    }

    /**
     * Les numeros maliens sont saisis a 8 chiffres au comptoir ; la passerelle
     * les remet au format international attendu par SMSGate.
     */
    public function test_les_numeros_sont_mis_au_format_international(): void
    {
        foreach ([
            '76445566' => '+22376445566',
            '76 44 55 66' => '+22376445566',
            '+223 76 44 55 66' => '+22376445566',
            '0022376445566' => '+22376445566',
            '22376445566' => '+22376445566',
        ] as $saisie => $attendu) {
            $http = new HttpFactory;
            $http->fake(['*' => $http->response([], 202)]);

            (new SmsGateway($http, 'https://sms.local', 'u', 'p'))->send((string) $saisie, 'Test');

            $http->assertSent(fn (Request $request) => $request['phoneNumbers'] === [$attendu]);
        }
    }

    public function test_un_envoi_est_ignore_si_la_passerelle_est_desactivee(): void
    {
        $http = new HttpFactory;
        $http->fake();

        $gateway = new SmsGateway($http, 'https://sms.local', 'u', 'p', enabled: false);

        $this->assertFalse($gateway->send('76445566', 'Bonjour'));
        $http->assertNothingSent();
    }

    public function test_un_envoi_est_ignore_si_l_url_n_est_pas_configuree(): void
    {
        $http = new HttpFactory;
        $http->fake();

        $this->assertFalse((new SmsGateway($http, null, null, null))->send('76445566', 'Bonjour'));
        $http->assertNothingSent();
    }

    /**
     * Une passerelle injoignable ne doit jamais faire echouer l'acte metier
     * deja valide (enregistrement, renvoi, resultat).
     */
    public function test_une_passerelle_injoignable_renvoie_false_sans_lever_d_exception(): void
    {
        $http = new HttpFactory;
        $http->fake(['*' => $http->failedConnection()]);

        $this->assertFalse((new SmsGateway($http, 'https://sms.local', 'u', 'p'))->send('76445566', 'Bonjour'));
    }

    public function test_une_reponse_en_erreur_renvoie_false(): void
    {
        $http = new HttpFactory;
        $http->fake(['*' => $http->response(['error' => 'refuse'], 500)]);

        $this->assertFalse((new SmsGateway($http, 'https://sms.local', 'u', 'p'))->send('76445566', 'Bonjour'));
    }

    public function test_un_numero_ou_un_texte_vide_n_envoie_rien(): void
    {
        $http = new HttpFactory;
        $http->fake();

        $gateway = new SmsGateway($http, 'https://sms.local', 'u', 'p');

        $this->assertFalse($gateway->send('', 'Bonjour'));
        $this->assertFalse($gateway->send('76445566', '   '));
        $http->assertNothingSent();
    }
}
