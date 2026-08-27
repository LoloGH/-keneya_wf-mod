<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Setting;
use App\Services\StaffNotifier;
use Illuminate\Console\Command;

/**
 * Rappel des rendez-vous qui approchent (v3.2.3, point 2).
 *
 * Seul declencheur de notification qui ne repond pas a un geste humain : il
 * faut donc que quelque chose passe regulierement. C'est la premiere tache
 * planifiee reelle de l'application — voir le service `scheduler` du
 * docker-compose, sans lequel cette commande ne s'executerait jamais.
 *
 * `reminder_sent_at` garantit un rappel et un seul : la commande repasse
 * toutes les quinze minutes sur une fenetre qui se recouvre largement.
 */
class SendAppointmentReminders extends Command
{
    protected $signature = 'keneya:rappels-rendez-vous';

    protected $description = 'Notifie les medecins des rendez-vous qui approchent.';

    public function handle(StaffNotifier $notifier): int
    {
        $minutes = (int) Setting::get(
            Setting::APPOINTMENT_REMINDER_MINUTES,
            (string) Setting::DEFAULT_APPOINTMENT_REMINDER_MINUTES,
        );

        if ($minutes < 1) {
            $this->info('Rappels desactives (delai nul ou negatif).');

            return self::SUCCESS;
        }

        // Fenetre : d'ici a `minutes` minutes. Un rendez-vous deja passe n'est
        // pas rappele — le rappel arriverait apres le patient.
        $rendezVous = Appointment::with(['patient', 'doctor.user'])
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->whereNull('reminder_sent_at')
            ->whereBetween('scheduled_at', [now(), now()->addMinutes($minutes)])
            ->get();

        $envoyes = 0;

        foreach ($rendezVous as $appointment) {
            if ($notifier->appointmentReminder($appointment) > 0) {
                $envoyes++;
            }

            // Marque meme sans destinataire : un rendez-vous dont le medecin a
            // ete supprime ne doit pas etre reexamine a chaque passage.
            $appointment->forceFill(['reminder_sent_at' => now()])->save();
        }

        $this->info(sprintf('%d rappel(s) envoye(s) sur %d rendez-vous.', $envoyes, $rendezVous->count()));

        return self::SUCCESS;
    }
}
