<?php

declare(strict_types=1);

namespace Keneya\Dme\Sms\Pipeline;

use Keneya\Dme\Models\SmsMessage;
use Keneya\Dme\Models\SmsTemplate;
use Keneya\Dme\Support\PhoneNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Service SMS transversal (§35, §53).
 *
 * Point d'entrée unique de tout envoi. Le flux est toujours le même :
 *
 *   événement métier -> SmsService -> file d'attente -> passerelle
 *   -> statut -> historique
 *
 * Un message est d'abord persisté (statut « pending »), puis mis en file.
 * Ainsi, aucun SMS n'est perdu si la passerelle est indisponible, et
 * l'échec d'un envoi ne peut jamais interrompre un acte médical.
 *
 * Découplage volontaire : ce service ne connaît ni Patient, ni Rendez-vous.
 * Il reçoit un numéro, un texte et un contexte optionnel (type + id) sous
 * forme scalaire. C'est ce qui permettra de l'extraire en phase 2 pour en
 * faire le service SMS partagé de Keneya Workflow.
 */
class SmsService
{
    public function __construct(private readonly SmsGatewayManager $gateways)
    {
    }

    /**
     * Prépare et met en file un message libre.
     *
     * @param  array{patient_id?: int|null, context_type?: string|null, context_id?: int|null}  $context
     */
    public function send(
        string $recipient,
        string $body,
        array $context = [],
        ?Carbon $scheduledFor = null,
        ?SmsTemplate $template = null,
    ): SmsMessage {
        $normalized = PhoneNumber::normalize($recipient);

        if ($normalized === null) {
            throw new RuntimeException('Numéro de destinataire invalide.');
        }

        $message = SmsMessage::create([
            'reference' => 'SMS-'.Str::upper(Str::random(10)),
            'recipient' => $normalized,
            'body' => $body,
            'sender' => config('dme.sms.sender'),
            'sms_template_id' => $template?->getKey(),
            'patient_id' => $context['patient_id'] ?? null,
            'context_type' => $context['context_type'] ?? null,
            'context_id' => $context['context_id'] ?? null,
            'status' => 'pending',
            'scheduled_for' => $scheduledFor,
            'created_by' => Auth::id(),
        ]);

        return $this->dispatch($message);
    }

    /**
     * Envoie un message construit à partir d'un modèle de texte (§36).
     *
     * @param  array<string, string|int|null>  $variables
     * @param  array{patient_id?: int|null, context_type?: string|null, context_id?: int|null}  $context
     */
    public function sendTemplate(
        string $templateKey,
        string $recipient,
        array $variables = [],
        array $context = [],
        ?Carbon $scheduledFor = null,
    ): ?SmsMessage {
        $template = SmsTemplate::where('key', $templateKey)->where('is_active', true)->first();

        // Un modèle absent ou désactivé n'est pas une erreur bloquante :
        // la notification métier a déjà eu lieu, seul le SMS est omis.
        if ($template === null) {
            return null;
        }

        return $this->send(
            $recipient,
            $template->render($variables),
            $context,
            $scheduledFor,
            $template,
        );
    }

    /**
     * Place le message dans la file d'attente configurée.
     */
    public function dispatch(SmsMessage $message): SmsMessage
    {
        $message->update(['status' => 'queued']);

        $job = SendSmsMessage::dispatch($message->id)
            ->onQueue((string) config('dme.sms.queue.name', 'sms'));

        if ($connection = config('dme.sms.queue.connection')) {
            $job->onConnection($connection);
        }

        if ($message->scheduled_for !== null && $message->scheduled_for->isFuture()) {
            $job->delay($message->scheduled_for);
        }

        return $message->refresh();
    }

    /**
     * Envoi effectif auprès de la passerelle. Appelé par le job ; ne doit
     * pas être invoqué directement depuis un contrôleur.
     *
     * Le statut enregistré est exactement celui rapporté par la
     * passerelle : « accepté » n'est jamais promu en « envoyé ». Une
     * passerelle qui se contente d'accuser réception laisse donc le
     * message en transit, jusqu'à ce que le suivi d'acheminement le
     * fasse évoluer.
     */
    public function deliver(SmsMessage $message): SmsResult
    {
        $gateway = $this->gateways->gateway();

        $result = $gateway->send($message->recipient, $message->body, $message->sender);

        $message->forceFill([
            'attempts' => $message->attempts + 1,
            'gateway' => $result->gateway,
            'gateway_response' => $result->response,
        ]);

        $this->applyResult($message, $result);

        return $result;
    }

    /**
     * Interroge la passerelle sur l'état d'un message en transit.
     *
     * Retourne null si la passerelle ne sait pas suivre l'acheminement,
     * ou si le message n'a pas d'identifiant fournisseur.
     */
    public function refreshStatus(SmsMessage $message): ?SmsResult
    {
        $gateway = $this->gateways->gateway();

        if (! $gateway instanceof TracksDeliveryStatus || blank($message->gateway_message_id)) {
            return null;
        }

        $result = $gateway->status((string) $message->gateway_message_id);

        $message->forceFill([
            'status_checked_at' => now(),
            'gateway_response' => $result->response ?: $message->gateway_response,
        ]);

        // Une erreur d'interrogation (réseau, 5xx) ne doit pas faire
        // basculer un message en échec : on n'a rien appris de son sort.
        if ($result->state === SmsResult::STATE_FAILED && $result->response === []) {
            $message->save();

            return $result;
        }

        $this->applyResult($message, $result);

        return $result;
    }

    /**
     * Reporte l'état rapporté par la passerelle sur le message.
     */
    private function applyResult(SmsMessage $message, SmsResult $result): void
    {
        $attributes = match ($result->state) {
            SmsResult::STATE_ACCEPTED => [
                'status' => 'accepted',
                'accepted_at' => $message->accepted_at ?? now(),
                'gateway_message_id' => $result->messageId,
                'error_message' => null,
            ],
            SmsResult::STATE_SENT => [
                'status' => 'sent',
                'accepted_at' => $message->accepted_at ?? now(),
                'sent_at' => $message->sent_at ?? now(),
                'gateway_message_id' => $result->messageId ?? $message->gateway_message_id,
                'error_message' => null,
            ],
            SmsResult::STATE_DELIVERED => [
                'status' => 'delivered',
                'sent_at' => $message->sent_at ?? now(),
                'delivered_at' => $message->delivered_at ?? now(),
                'gateway_message_id' => $result->messageId ?? $message->gateway_message_id,
                'error_message' => null,
            ],
            SmsResult::STATE_CANCELLED => [
                'status' => 'cancelled',
                'gateway_message_id' => $result->messageId ?? $message->gateway_message_id,
            ],
            default => [
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => $result->error,
            ],
        };

        $message->forceFill($attributes)->save();
    }

    /**
     * Rejoue un message en échec, dans la limite du quota d'essais.
     */
    public function retry(SmsMessage $message): SmsMessage
    {
        if (! $message->isRetryable()) {
            throw new RuntimeException(
                'Ce message a atteint le nombre maximal de tentatives et ne peut plus être rejoué.'
            );
        }

        $message->update(['status' => 'pending', 'error_message' => null]);

        return $this->dispatch($message);
    }
}
