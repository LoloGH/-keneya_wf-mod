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

    /**
     * Journal du module Dossier Medical Electronique (v3.3.0).
     *
     * Le module ecrit dans la meme table `activity_log`, sous son propre nom
     * de journal. Les deux cohabitent a dessein : l'administration de
     * l'hopital doit pouvoir relire, au meme endroit, qui a ouvert un dossier
     * medical et qui a cloture un passage. Deux ecrans separes auraient
     * garanti que le second ne soit jamais lu.
     */
    public const MEDICAL_LOG_NAME = 'medical';

    /**
     * Les journaux que l'ecran d'audit de /admin restitue.
     *
     * @var array<int, string>
     */
    public const LOG_NAMES = [self::LOG_NAME, self::MEDICAL_LOG_NAME];

    // ------------------------------------------------------------------
    // Evenements ecrits par le module DME. Les noms viennent du module et
    // restent en anglais : ce sont des valeurs deja en base, on ne les
    // renomme pas — seul leur libelle d'affichage est traduit ci-dessous.
    // ------------------------------------------------------------------

    /** Consultation d'un dossier medical (trace a chaque ouverture). */
    public const EVENT_DME_VIEWED = 'viewed';

    /** Tentative d'acces refusee a l'interieur du dossier medical. */
    public const EVENT_DME_DENIED = 'denied';

    /** Telechargement d'un document medical. */
    public const EVENT_DME_DOWNLOADED = 'downloaded';

    /** Acces au module refuse : l'hote ne l'a pas accorde. */
    public const EVENT_DME_ACCESS_DENIED = 'dme_access_denied';

    public const EVENT_LOGIN = 'connexion';

    public const EVENT_LOGOUT = 'deconnexion';

    /**
     * Changement de mot de passe (v3.2.3, point 3). L'evenement est journalise,
     * jamais le mot de passe — ni l'ancien, ni le nouveau.
     */
    public const EVENT_PASSWORD_CHANGED = 'mot_de_passe_change';

    public const EVENT_PATIENT_CREATED = 'patient_cree';

    public const EVENT_PATIENT_UPDATED = 'patient_modifie';

    /**
     * Creation d'un dossier malgre un doublon probable signale (v3.2.8, point 1).
     *
     * On n'empeche pas la receptionniste de passer outre — elle voit la
     * personne, pas nous. Mais la decision est tracee, pour qu'un doublon
     * eventuel reste explicable plutot que silencieux.
     */
    public const EVENT_DUPLICATE_OVERRIDDEN = 'doublon_ignore';

    public const EVENT_VISIT_OPENED = 'episode_ouvert';

    public const EVENT_VISIT_CLOSED = 'dossier_cloture';

    public const EVENT_REFERRAL_SENT = 'renvoi_envoye';

    public const EVENT_REFERRAL_COMPLETED = 'resultat_saisi';

    public const EVENT_REFERRAL_CLOSED = 'renvoi_cloture';

    public const EVENT_SERVICE_CREATED = 'service_cree';

    public const EVENT_SERVICE_UPDATED = 'service_modifie';

    public const EVENT_SERVICE_DELETED = 'service_supprime';

    public const EVENT_SERVICE_KIND_CREATED = 'type_service_cree';

    public const EVENT_SERVICE_KIND_UPDATED = 'type_service_modifie';

    public const EVENT_SERVICE_KIND_DELETED = 'type_service_supprime';

    public const EVENT_STAFF_TYPE_CREATED = 'type_personnel_cree';

    public const EVENT_STAFF_TYPE_UPDATED = 'type_personnel_modifie';

    public const EVENT_STAFF_TYPE_DELETED = 'type_personnel_supprime';

    public const EVENT_STAFF_MEMBER_CREATED = 'personnel_cree';

    public const EVENT_STAFF_DELETED = 'personnel_supprime';

    public const EVENT_PATIENT_ADMITTED = 'patient_hospitalise';

    public const EVENT_PATIENT_DISCHARGED = 'sortie_hospitalisation';

    public const EVENT_CARE_TASKS_PRESCRIBED = 'soins_prescrits';

    public const EVENT_CARE_TASK_COMPLETED = 'soin_realise';

    /** Correction d'un soin deja programme (v3.2.3, point 4). */
    public const EVENT_CARE_TASK_REVISED = 'soin_modifie';

    /** Annulation d'un soin : la ligne reste, elle ne compte plus. */
    public const EVENT_CARE_TASK_CANCELLED = 'soin_annule';

    /** Note de releve entre equipes (v3.2.3, point 4). */
    public const EVENT_HANDOFF_NOTE = 'note_de_releve';

    public const EVENT_DOCTOR_CREATED = 'medecin_cree';

    public const EVENT_DOCTOR_REASSIGNED = 'medecin_reaffecte';

    public const EVENT_RECEPTIONIST_CREATED = 'receptionniste_creee';

    public const EVENT_PAYMENT_RECORDED = 'encaissement';

    /**
     * Changement d'une signature ou d'un tampon (v3.2.9, point 2).
     *
     * Ces trois images valent engagement sur une ordonnance : leur
     * remplacement ne doit jamais passer inapercu.
     */
    public const EVENT_SIGNATURE_CHANGED = 'signature_modifiee';

    public const EVENT_PRESCRIPTION_CREATED = 'ordonnance_creee';

    public const EVENT_APPOINTMENT_CREATED = 'rendez_vous_cree';

    public const EVENT_SCHEDULE_CHANGED = 'planning_modifie';

    public const EVENT_SCHEDULE_BULK = 'planning_groupe';

    public const EVENT_PATIENT_CALLED = 'patient_appele';

    public const EVENT_PAYMENT_CONFIRMED = 'paiement_confirme';

    /**
     * Montant encaisse different du tarif du catalogue (v3.2.8, point 3).
     *
     * Un evenement a part, et non une propriete de l'encaissement ordinaire :
     * noyee parmi tous les paiements de la journee, une derogation serait
     * introuvable — or c'est precisement ce qu'un gestionnaire veut retrouver.
     */
    public const EVENT_PRICE_OVERRIDDEN = 'tarif_deroge';

    public const EVENT_CONCLUSION_RECORDED = 'conclusion_redigee';

    /**
     * Consultation medicale redigee depuis /service (v3.3.1).
     *
     * Distincte de la conclusion : celle-ci est un acte du dossier medical,
     * avec son numero, ses constantes et ses diagnostics. Le journal n'en
     * porte que la reference — le contenu clinique reste au DME.
     */
    public const EVENT_MEDICAL_CONSULTATION = 'consultation_medicale';

    /** Antecedent consigne au dossier medical (v3.3.1). */
    public const EVENT_MEDICAL_BACKGROUND = 'antecedent_consigne';

    /**
     * Allergie consignee, resolue ou refutee (v3.3.1).
     *
     * Journalisee comme une action a part : c'est elle que le module lit pour
     * signaler un conflit au moment de prescrire, et savoir qui l'a saisie ou
     * ecartee peut compter.
     */
    public const EVENT_ALLERGY_RECORDED = 'allergie_consignee';

    public const EVENT_ATTACHMENT_ADDED = 'piece_jointe_ajoutee';

    public const EVENT_VISITOR_REGISTERED = 'visiteur_enregistre';

    /**
     * Diffusion groupee de SMS depuis l'administration (v3.2.9, point 1).
     *
     * Le contenu integral du message et le nombre de destinataires partent
     * dans les proprietes : s'adresser d'un coup a des centaines de patients
     * demande de pouvoir relire, des mois plus tard, ce qui leur a ete dit.
     */
    public const EVENT_BROADCAST_SENT = 'diffusion_sms';

    public const EVENT_PORTAL_LINK_SENT = 'lien_portail_envoye';

    public const EVENT_PORTAL_ACCESS = 'consultation_portail';

    /** Depot d'un retour, d'une reclamation ou d'un constat (v3.2.8, point 4). */
    public const EVENT_FEEDBACK_RECORDED = 'retour_depose';

    /** Traitement d'un retour par l'administration, avec sa note de resolution. */
    public const EVENT_FEEDBACK_RESOLVED = 'retour_traite';

    public const EVENT_PATIENT_DELETED = 'patient_supprime';

    /** Modifications d'attributs captees automatiquement par LogsActivity. */
    public const EVENT_CREATED = 'created';

    public const EVENT_UPDATED = 'updated';

    public const EVENT_DELETED = 'deleted';

    /**
     * Libelles francais des evenements, pour l'affichage et le filtre.
     *
     * @var array<string, string>
     */
    public const LABELS = [
        self::EVENT_LOGIN => 'Connexion',
        self::EVENT_LOGOUT => 'Deconnexion',
        self::EVENT_PASSWORD_CHANGED => 'Changement de mot de passe',
        self::EVENT_PATIENT_CREATED => 'Creation d\'un patient',
        self::EVENT_PATIENT_UPDATED => 'Modification d\'un patient',
        self::EVENT_DUPLICATE_OVERRIDDEN => 'Doublon signale, dossier cree malgre tout',
        self::EVENT_VISIT_OPENED => 'Ouverture d\'un episode',
        self::EVENT_VISIT_CLOSED => 'Cloture d\'un dossier',
        self::EVENT_REFERRAL_SENT => 'Envoi d\'un renvoi',
        self::EVENT_REFERRAL_COMPLETED => 'Saisie d\'un resultat',
        self::EVENT_REFERRAL_CLOSED => 'Cloture d\'un renvoi',
        self::EVENT_SERVICE_CREATED => 'Creation d\'un service',
        self::EVENT_SERVICE_UPDATED => 'Modification d\'un service',
        self::EVENT_SERVICE_DELETED => 'Suppression d\'un service',
        self::EVENT_SERVICE_KIND_CREATED => 'Creation d\'un type de service',
        self::EVENT_SERVICE_KIND_UPDATED => 'Modification d\'un type de service',
        self::EVENT_SERVICE_KIND_DELETED => 'Suppression d\'un type de service',
        self::EVENT_STAFF_TYPE_CREATED => 'Creation d\'un type de personnel',
        self::EVENT_STAFF_TYPE_UPDATED => 'Modification d\'un type de personnel',
        self::EVENT_STAFF_TYPE_DELETED => 'Suppression d\'un type de personnel',
        self::EVENT_STAFF_MEMBER_CREATED => 'Creation d\'un membre du personnel',
        self::EVENT_STAFF_DELETED => 'Suppression d\'un membre du personnel',
        self::EVENT_PATIENT_ADMITTED => 'Admission en hospitalisation',
        self::EVENT_PATIENT_DISCHARGED => 'Sortie d\'hospitalisation',
        self::EVENT_CARE_TASKS_PRESCRIBED => 'Prescription de soins',
        self::EVENT_CARE_TASK_COMPLETED => 'Soin realise',
        self::EVENT_CARE_TASK_REVISED => 'Soin corrige',
        self::EVENT_CARE_TASK_CANCELLED => 'Soin annule',
        self::EVENT_HANDOFF_NOTE => 'Note de releve',
        self::EVENT_DOCTOR_CREATED => 'Creation d\'un medecin',
        self::EVENT_DOCTOR_REASSIGNED => 'Reaffectation d\'un medecin',
        self::EVENT_RECEPTIONIST_CREATED => 'Creation d\'une receptionniste',
        self::EVENT_PAYMENT_RECORDED => 'Encaissement',
        self::EVENT_SIGNATURE_CHANGED => 'Modification d\'une signature ou d\'un tampon',
        self::EVENT_PRESCRIPTION_CREATED => 'Creation d\'une ordonnance',
        self::EVENT_APPOINTMENT_CREATED => 'Prise de rendez-vous',
        self::EVENT_SCHEDULE_CHANGED => 'Modification d\'un planning',
        self::EVENT_SCHEDULE_BULK => 'Creation groupee de planning',
        self::EVENT_PATIENT_CALLED => 'Appel du patient suivant',
        self::EVENT_PAYMENT_CONFIRMED => 'Confirmation de paiement',
        self::EVENT_PRICE_OVERRIDDEN => 'Derogation au tarif',
        self::EVENT_CONCLUSION_RECORDED => 'Conclusion de consultation',
        self::EVENT_MEDICAL_CONSULTATION => 'Consultation medicale (dossier medical)',
        self::EVENT_MEDICAL_BACKGROUND => 'Antecedent consigne au dossier medical',
        self::EVENT_ALLERGY_RECORDED => 'Allergie consignee au dossier medical',
        self::EVENT_ATTACHMENT_ADDED => 'Ajout d\'une piece jointe',
        self::EVENT_VISITOR_REGISTERED => 'Enregistrement d\'un visiteur',
        self::EVENT_BROADCAST_SENT => 'Diffusion groupee de SMS',
        self::EVENT_PORTAL_LINK_SENT => 'Envoi du lien de documents',
        self::EVENT_PORTAL_ACCESS => 'Consultation du portail patient',
        self::EVENT_FEEDBACK_RECORDED => 'Depot d\'un retour',
        self::EVENT_FEEDBACK_RESOLVED => 'Traitement d\'un retour',
        self::EVENT_PATIENT_DELETED => 'Suppression d\'un dossier patient',
        self::EVENT_CREATED => 'Creation',
        self::EVENT_UPDATED => 'Modification',
        self::EVENT_DELETED => 'Suppression',
        self::EVENT_DME_VIEWED => 'Consultation du dossier medical',
        self::EVENT_DME_DENIED => 'Acces refuse dans le dossier medical',
        self::EVENT_DME_DOWNLOADED => 'Telechargement d\'un document medical',
        self::EVENT_DME_ACCESS_DENIED => 'Acces au dossier medical refuse',
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
