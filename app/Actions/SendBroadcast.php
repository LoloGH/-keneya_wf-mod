<?php

namespace App\Actions;

use App\Jobs\SendSmsJob;
use App\Models\BroadcastMessage;
use App\Models\User;
use App\Services\BroadcastRecipients;
use App\Support\Audit;
use App\Support\BroadcastTarget;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Diffusion d'un SMS a un groupe (v3.2.9, point 1).
 *
 * Aucun envoi direct : chaque destinataire passe par `SendSmsJob`, comme tout
 * le reste de l'application depuis la v3.2.8. Une diffusion vers un millier de
 * patients rend donc la main immediatement, et c'est le worker qui affronte la
 * passerelle : un envoi groupe synchrone aurait fige l'ecran de
 * l'administrateur pendant plusieurs minutes, quand il ne l'aurait pas fait
 * expirer.
 */
class SendBroadcast
{
    public function __construct(private readonly BroadcastRecipients $recipients) {}

    public function execute(User $sender, string $content, BroadcastTarget $target): BroadcastMessage
    {
        $content = trim($content);

        if ($content === '') {
            throw new InvalidArgumentException('Le message ne peut pas etre vide.');
        }

        $attendus = $this->recipients->count($target);

        if ($attendus === 0) {
            throw new InvalidArgumentException(
                'Aucun destinataire joignable ne correspond a cette selection : personne n\'a de numero de telephone.'
            );
        }

        // La diffusion est enregistree avant le premier envoi : c'est elle que
        // chaque `sms_messages` designera, et une trace posee apres coup
        // manquerait justement en cas d'interruption.
        $diffusion = BroadcastMessage::create([
            'sent_by_user_id' => $sender->getKey(),
            'content' => $content,
            'target_type' => $target->type,
            'target_filters' => $target->filters() ?: null,
            'recipient_count' => $attendus,
        ]);

        $envoyes = $this->recipients->each(
            $target,
            fn (string $numero) => SendSmsJob::dispatch($numero, $content, $diffusion),
        );

        // Le decompte reel prime sur l'estime : entre l'apercu et l'envoi, un
        // dossier a pu etre cree ou supprime. C'est le nombre de SMS
        // reellement mis en file qui fait foi.
        if ($envoyes !== $attendus) {
            $diffusion->forceFill(['recipient_count' => $envoyes])->save();
        }

        Audit::log(
            Audit::EVENT_BROADCAST_SENT,
            sprintf(
                'Diffusion SMS a %d destinataire(s) - %s. Message : « %s »',
                $envoyes,
                $diffusion->targetLabel(),
                Str::limit($content, 200),
            ),
            $diffusion,
            [
                'destinataires' => $envoyes,
                'cible' => $target->type,
                'filtres' => $target->filters(),
                // Le contenu integral, et non seulement son debut : une
                // diffusion est une action suffisamment sensible pour qu'on
                // puisse relire exactement ce qui a ete envoye.
                'message' => $content,
            ],
        );

        return $diffusion;
    }
}
