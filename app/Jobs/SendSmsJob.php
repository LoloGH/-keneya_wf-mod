<?php

namespace App\Jobs;

use App\Models\SmsMessage;
use App\Services\SmsGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

/**
 * Envoi d'un SMS en arriere-plan (v3.2.8, file d'attente SMS).
 *
 * Avant : l'agent d'accueil enregistrait un patient, et l'ecran restait fige
 * le temps que la passerelle reponde — quelques secondes par patient, sur un
 * telephone Android que Doze peut avoir endormi. L'acte metier est desormais
 * termine des que la ligne `sms_messages` est ecrite ; c'est le worker qui
 * attend la passerelle, et lui seul.
 */
class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;
    use Queueable;

    /**
     * Trois tentatives, espacees de 30 s, 60 s puis 120 s. Les memes valeurs
     * sont passees au worker dans docker-compose.yml ; les declarer ici aussi
     * garantit le meme comportement quel que soit le worker qui ramasse le job
     * (un `queue:work` lance a la main pendant une intervention, par exemple).
     *
     * @var int
     */
    public $tries = 3;

    /**
     * @var array<int, int>
     */
    public $backoff = [30, 60, 120];

    public function __construct(public readonly int $smsMessageId) {}

    /**
     * Met un SMS en file et rend la trace creee.
     *
     * Surcharge volontaire du `dispatch()` de `Dispatchable` : les actions
     * metier appellent `SendSmsJob::dispatch($numero, $texte, $objet)` sans
     * avoir a creer la ligne de trace elles-memes, et un numero absent est
     * ecarte ici plutot que de remplir la table d'echecs previsibles.
     */
    public static function dispatch(?string $to, string $body, ?Model $related = null): ?SmsMessage
    {
        if (blank($to) || blank(trim($body))) {
            return null;
        }

        $message = SmsMessage::create([
            'to' => $to,
            'body' => $body,
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
            'status' => SmsMessage::STATUS_QUEUED,
        ]);

        // `afterCommit` : quand l'envoi est declenche depuis une transaction,
        // le worker ne doit pas ramasser le job avant que la ligne de trace —
        // ecrite dans la meme transaction — soit visible pour lui.
        dispatch(new self($message->getKey()))->afterCommit();

        return $message;
    }

    public function handle(SmsGateway $gateway): void
    {
        $message = SmsMessage::find($this->smsMessageId);

        // La trace a disparu (purge, dossier supprime) : plus rien a envoyer,
        // et surtout rien a tracer — on sort sans echouer le job.
        if (! $message) {
            return;
        }

        $result = $gateway->deliver($message->to, $message->body);

        $message->forceFill([
            'attempts' => $this->attempts(),
            'provider_message_id' => $result->providerMessageId ?? $message->provider_message_id,
        ]);

        if ($result->successful) {
            $message->forceFill([
                'status' => SmsMessage::STATUS_SENT,
                'failure_reason' => null,
                'sent_at' => now(),
            ])->save();

            return;
        }

        $message->forceFill(['failure_reason' => $result->failureReason])->save();

        // Echec definitif (passerelle desactivee, identifiants refuses) : rien
        // a gagner a reessayer, on marque et on s'arrete la.
        if (! $result->retryable) {
            $this->markFailed($message, $result->failureReason);
            $this->fail(new RuntimeException((string) $result->failureReason));

            return;
        }

        // Echec passager : le job repart en file avec l'attente prevue.
        //
        // `release()` plutot qu'une exception levee : sous le pilote `sync` —
        // celui des tests et de bien des postes de developpement — une
        // exception remonterait jusqu'a l'action metier appelante, et un SMS
        // non parti recasserait l'enregistrement que cette file est justement
        // censee proteger. `release()` tient cette promesse quel que soit le
        // pilote, et le worker conclura en `failed` a la troisieme tentative.
        $this->release($this->delaiAvantNouvelleTentative());
    }

    /**
     * Attente avant la prochaine tentative, lue dans `$backoff` selon le
     * numero de la tentative qui vient d'echouer.
     */
    private function delaiAvantNouvelleTentative(): int
    {
        $paliers = $this->backoff;

        return (int) ($paliers[$this->attempts() - 1] ?? end($paliers));
    }

    /**
     * Appele une fois les trois tentatives epuisees. C'est ce statut `failed`,
     * et lui seul, que compte l'indicateur « X echecs dans les dernieres 24 h ».
     */
    public function failed(?Throwable $exception): void
    {
        $message = SmsMessage::find($this->smsMessageId);

        if ($message) {
            $this->markFailed($message, $exception?->getMessage());
        }
    }

    /**
     * La raison deja consignee par `handle()` prime sur le message de
     * l'exception : « La passerelle a repondu 500 » se lit mieux, dans la
     * liste de l'administration, que « has been attempted too many times ».
     */
    private function markFailed(SmsMessage $message, ?string $reason): void
    {
        $message->forceFill([
            'status' => SmsMessage::STATUS_FAILED,
            'failure_reason' => $message->failure_reason ?: $reason,
        ])->save();
    }
}
