<?php

namespace App\Services\Dme;

use App\Jobs\SendSmsJob;
use Illuminate\Database\Eloquent\Model;
use Keneya\Dme\Contracts\SmsDispatcherContract;
use Keneya\Dme\Sms\SmsContext;
use Throwable;

/**
 * Envoi des SMS du module DME par la file d'attente de WorkFlow (v3.3.0).
 *
 * Le module ne connait que `SmsDispatcherContract` ; c'est l'hote qui decide
 * ce qu'il y a derriere. Assemble ici, il n'y a plus qu'un seul chemin :
 * `SendSmsJob`, donc une seule table `sms_messages`, un seul worker, une
 * seule politique de reessai, et un seul indicateur d'echecs dans /admin.
 *
 * C'est la raison d'etre de cette classe : sans elle, le module retomberait
 * sur sa propre file interne et l'etablissement aurait deux historiques de
 * SMS a surveiller, dont un que personne ne regarde.
 */
class WorkflowSmsDispatcher implements SmsDispatcherContract
{
    /**
     * Remet un message a la file de WorkFlow.
     *
     * Un SMS manque ne doit jamais interrompre un acte medical : le contrat
     * l'exige, et `SendSmsJob::dispatch()` s'y tient deja — il ecarte un
     * numero vide sans lever, puis c'est le worker qui affronte la
     * passerelle. Rien n'est donc rattrape ici en dehors de la resolution du
     * contexte, qui est purement decorative.
     */
    public function dispatch(string $to, string $message, ?string $context = null): void
    {
        SendSmsJob::dispatch($to, $message, $this->relatedFrom($context));
    }

    /**
     * Retrouve l'enregistrement du DME a l'origine du message, pour que la
     * ligne `sms_messages` pointe vers lui comme n'importe quel SMS emis par
     * WorkFlow.
     *
     * Purement indicatif : un contexte illisible, un modele disparu ou une
     * classe inconnue ne doivent jamais empecher le message de partir.
     */
    private function relatedFrom(?string $context): ?Model
    {
        if ($context === null) {
            return null;
        }

        $parsed = SmsContext::parse($context);

        if ($parsed->subjectType === null || $parsed->subjectId === null) {
            return null;
        }

        if (! is_subclass_of($parsed->subjectType, Model::class)) {
            return null;
        }

        try {
            return $parsed->subjectType::find($parsed->subjectId);
        } catch (Throwable) {
            return null;
        }
    }
}
