<?php

namespace App\Console\Commands;

use App\Actions\SendFeedbackInvitation;
use App\Models\Setting;
use App\Models\Visitor;
use Illuminate\Console\Command;

/**
 * Invitation differee des visiteurs a donner leur avis (v3.2.8, point 4).
 *
 * On n'interroge pas quelqu'un a la seconde ou il franchit la porte : le delai
 * se regle dans les parametres plutot que dans le code, un etablissement
 * jugeant trois heures trop court quand un autre les jugera trop longues.
 *
 * Comme les rappels de rendez-vous, cette commande ne s'execute que si le
 * service `scheduler` tourne reellement.
 */
class SendVisitorFeedbackLinks extends Command
{
    protected $signature = 'keneya:liens-avis-visiteurs';

    protected $description = 'Envoie aux visiteurs le lien pour donner leur avis, une fois le delai ecoule.';

    public function handle(SendFeedbackInvitation $invitation): int
    {
        $heures = (int) Setting::get(
            Setting::VISITOR_FEEDBACK_DELAY_HOURS,
            (string) Setting::DEFAULT_VISITOR_FEEDBACK_DELAY_HOURS,
        );

        if ($heures < 1) {
            $this->info('Invitations desactivees (delai nul ou negatif).');

            return self::SUCCESS;
        }

        $visiteurs = Visitor::query()
            ->whereNull('feedback_link_sent_at')
            ->where('created_at', '<=', now()->subHours($heures))
            ->orderBy('id')
            ->get();

        $envoyes = 0;
        $sansNumero = 0;

        foreach ($visiteurs as $visitor) {
            // Le champ `mobile` est facultatif a l'accueil. Un visiteur sans
            // numero est marque comme traite sans qu'on tente quoi que ce soit :
            // il ne doit ni faire echouer l'execution, ni etre reexamine a
            // chaque passage. Il reste compte, et ce compte est visible dans
            // /admin : le cas serait invisible autrement.
            if (blank($visitor->mobile)) {
                $sansNumero++;
            } else {
                $invitation->toVisitor($visitor);
                $envoyes++;
            }

            // Marque dans tous les cas : c'est cette marque, et non l'envoi,
            // qui garantit qu'un visiteur ne recoit jamais deux liens.
            $visitor->forceFill(['feedback_link_sent_at' => now()])->save();
        }

        $this->info(sprintf(
            '%d lien(s) envoye(s), %d visiteur(s) sans numero ignore(s), sur %d visiteur(s) examine(s).',
            $envoyes,
            $sansNumero,
            $visiteurs->count(),
        ));

        return self::SUCCESS;
    }
}
