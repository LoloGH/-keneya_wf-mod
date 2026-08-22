<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Contracts\Activity;

/**
 * Point d'entree unique du journal d'audit.
 *
 * S'appuie sur spatie/laravel-activitylog. Les libelles sont en francais :
 * le journal est lu par l'administration de l'hopital, pas par un
 * developpeur.
 */
final class Audit
{
    public const LOG_NAME = 'keneya';

    public const EVENT_LOGIN = 'connexion';

    public const EVENT_LOGOUT = 'deconnexion';

    public const EVENT_PATIENT_CREATED = 'patient_cree';

    public const EVENT_PATIENT_UPDATED = 'patient_modifie';

    public const EVENT_VISIT_OPENED = 'episode_ouvert';

    public const EVENT_VISIT_CLOSED = 'dossier_cloture';

    public const EVENT_REFERRAL_SENT = 'renvoi_envoye';

    public const EVENT_REFERRAL_COMPLETED = 'resultat_saisi';

    public const EVENT_REFERRAL_CLOSED = 'renvoi_cloture';

    public const EVENT_SERVICE_CREATED = 'service_cree';

    public const EVENT_SERVICE_UPDATED = 'service_modifie';

    public const EVENT_SERVICE_DELETED = 'service_supprime';

    public const EVENT_DOCTOR_CREATED = 'medecin_cree';

    public const EVENT_DOCTOR_REASSIGNED = 'medecin_reaffecte';

    public const EVENT_RECEPTIONIST_CREATED = 'receptionniste_creee';

    public const EVENT_PAYMENT_RECORDED = 'encaissement';

    public const EVENT_PRESCRIPTION_CREATED = 'ordonnance_creee';

    public const EVENT_APPOINTMENT_CREATED = 'rendez_vous_cree';

    public const EVENT_SCHEDULE_CHANGED = 'planning_modifie';

    /**
     * Libelles francais des evenements, pour l'affichage et le filtre.
     *
     * @var array<string, string>
     */
    public const LABELS = [
        self::EVENT_LOGIN => 'Connexion',
        self::EVENT_LOGOUT => 'Deconnexion',
        self::EVENT_PATIENT_CREATED => 'Creation d\'un patient',
        self::EVENT_PATIENT_UPDATED => 'Modification d\'un patient',
        self::EVENT_VISIT_OPENED => 'Ouverture d\'un episode',
        self::EVENT_VISIT_CLOSED => 'Cloture d\'un dossier',
        self::EVENT_REFERRAL_SENT => 'Envoi d\'un renvoi',
        self::EVENT_REFERRAL_COMPLETED => 'Saisie d\'un resultat',
        self::EVENT_REFERRAL_CLOSED => 'Cloture d\'un renvoi',
        self::EVENT_SERVICE_CREATED => 'Creation d\'un service',
        self::EVENT_SERVICE_UPDATED => 'Modification d\'un service',
        self::EVENT_SERVICE_DELETED => 'Suppression d\'un service',
        self::EVENT_DOCTOR_CREATED => 'Creation d\'un medecin',
        self::EVENT_DOCTOR_REASSIGNED => 'Reaffectation d\'un medecin',
        self::EVENT_RECEPTIONIST_CREATED => 'Creation d\'une receptionniste',
        self::EVENT_PAYMENT_RECORDED => 'Encaissement',
        self::EVENT_PRESCRIPTION_CREATED => 'Creation d\'une ordonnance',
        self::EVENT_APPOINTMENT_CREATED => 'Prise de rendez-vous',
        self::EVENT_SCHEDULE_CHANGED => 'Modification d\'un planning',
    ];

    /**
     * @param  array<string, mixed>  $properties
     */
    public static function log(string $event, string $description, ?Model $subject = null, array $properties = []): ?Activity
    {
        $logger = activity(self::LOG_NAME)
            ->event($event)
            ->withProperties($properties);

        if ($user = Auth::user()) {
            $logger->causedBy($user);
        }

        if ($subject) {
            $logger->performedOn($subject);
        }

        return $logger->log($description);
    }

    public static function label(?string $event): string
    {
        return self::LABELS[$event] ?? (string) $event;
    }
}
