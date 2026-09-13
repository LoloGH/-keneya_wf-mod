<?php

declare(strict_types=1);

namespace Keneya\Dme\Services\Notifications;

use Keneya\Dme\Contracts\DmeUser;
use Keneya\Dme\Dme;
use Keneya\Dme\Models\Appointment;
use Keneya\Dme\Models\LabOrder;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\Prescription;
use Keneya\Dme\Contracts\SmsDispatcherContract;
use Keneya\Dme\Models\SmsTemplate;
use Keneya\Dme\Sms\SmsContext;
use Keneya\Dme\Support\PhoneNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Centre de notifications (§33) et déclenchement des SMS métier (§53).
 *
 * C'est la seule couche qui connaît à la fois le domaine médical et
 * l'envoi de SMS. Les modèles et contrôleurs ne déclenchent jamais un
 * envoi directement : ils décrivent un événement métier, ce service
 * décide des destinataires internes (notification) et externes (SMS
 * patient).
 *
 * Le texte du message reste une affaire du DME : il provient des modèles
 * de texte de l'établissement. L'envoi, lui, ne l'est pas : il passe par
 * SmsDispatcherContract, sans que cette classe sache jamais qui l'assure.
 * C'est ce qui permettra à Keneya Workflow de fournir sa propre
 * implémentation sans qu'une ligne d'ici ne change.
 */
class NotificationService
{
    public function __construct(private readonly SmsDispatcherContract $sms)
    {
    }

    /**
     * Notifie les utilisateurs disposant d'une permission donnée.
     *
     * @param  array<string, mixed>  $data
     */
    public function notifyPermission(
        string $permission,
        string $category,
        string $title,
        string $message,
        ?Patient $patient = null,
        ?string $actionUrl = null,
        string $level = 'info',
        array $data = [],
    ): int {
        $recipients = Dme::userQuery()->permission($permission)->where('is_active', true)->get();
        $count = 0;

        foreach ($recipients as $recipient) {
            $this->store($recipient, $category, $title, $message, $patient, $actionUrl, $level, $data);
            $count++;
        }

        return $count;
    }

    /**
     * Écrit une notification pour un utilisateur.
     *
     * @param  array<string, mixed>  $data
     */
    public function store(
        DmeUser $user,
        string $category,
        string $title,
        string $message,
        ?Patient $patient = null,
        ?string $actionUrl = null,
        string $level = 'info',
        array $data = [],
    ): void {
        DB::table('dme_notifications')->insert([
            'id' => Str::uuid()->toString(),
            'type' => 'keneya.'.$category,
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'data' => json_encode(array_merge($data, [
                'title' => $title,
                'message' => $message,
            ]), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'category' => $category,
            'level' => $level,
            'patient_id' => $patient?->getKey(),
            'action_url' => $actionUrl,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // -----------------------------------------------------------------
    // Événements métier
    // -----------------------------------------------------------------

    /**
     * Rendez-vous créé : confirmation SMS au patient (§36).
     */
    public function appointmentScheduled(Appointment $appointment): void
    {
        $appointment->loadMissing(['patient', 'doctor']);
        $patient = $appointment->patient;

        $this->sendPatientSms($patient, 'appointment_scheduled', [
            'patient_name' => $patient->fullName(),
            'date' => $appointment->scheduled_for->translatedFormat('d F'),
            'time' => $appointment->scheduled_for->format('H\hi'),
            'doctor' => $appointment->doctor?->displayName() ?? '',
        ], $appointment);
    }

    /**
     * Rappel de rendez-vous : programmé la veille à la même heure.
     */
    public function appointmentReminder(Appointment $appointment): void
    {
        $appointment->loadMissing('patient');
        $patient = $appointment->patient;

        $reminderAt = $appointment->scheduled_for->copy()->subDay();

        if ($reminderAt->isPast()) {
            return;
        }

        $sent = $this->sendPatientSms($patient, 'appointment_reminder', [
            'patient_name' => $patient->fullName(),
            'time' => $appointment->scheduled_for->format('H\hi'),
        ], $appointment, $reminderAt);

        if ($sent) {
            $appointment->forceFill(['reminder_sent_at' => now()])->save();
        }
    }

    /**
     * Résultat de laboratoire disponible : notification interne au
     * prescripteur + SMS au patient.
     */
    public function labResultAvailable(LabOrder $order): void
    {
        $order->loadMissing(['patient', 'doctor']);
        $patient = $order->patient;

        if ($order->doctor !== null) {
            $this->store(
                user: $order->doctor,
                category: 'lab_result',
                title: 'Résultat disponible',
                message: 'Les résultats de '.$order->order_number.' sont disponibles pour '.$patient->fullName().'.',
                patient: $patient,
                actionUrl: route('dme.laboratory.show', $order),
                level: 'info',
            );
        }

        $this->sendPatientSms($patient, 'lab_result_available', [
            'patient_name' => $patient->fullName(),
            'reference' => $order->order_number,
        ], $order);
    }

    /**
     * Ordonnance validée : information du patient et des pharmaciens.
     */
    public function prescriptionValidated(Prescription $prescription): void
    {
        $prescription->loadMissing('patient');
        $patient = $prescription->patient;

        $this->notifyPermission(
            permission: 'prescriptions.dispense',
            category: 'prescription',
            title: 'Ordonnance à délivrer',
            message: $prescription->prescription_number.' - '.$patient->fullName(),
            patient: $patient,
            actionUrl: route('dme.prescriptions.show', $prescription),
        );

        $this->sendPatientSms($patient, 'prescription_ready', [
            'patient_name' => $patient->fullName(),
            'reference' => $prescription->prescription_number,
        ], $prescription);
    }

    /**
     * Résultat critique : alerte immédiate du prescripteur.
     */
    public function criticalResult(LabOrder $order, string $parameter, ?string $value): void
    {
        $order->loadMissing(['patient', 'doctor']);

        if ($order->doctor === null) {
            return;
        }

        $this->store(
            user: $order->doctor,
            category: 'alert',
            title: 'Résultat critique',
            message: $parameter.' = '.($value ?? '-').' pour '.$order->patient->fullName().'.',
            patient: $order->patient,
            actionUrl: route('dme.laboratory.show', $order),
            level: 'critical',
        );
    }

    /**
     * Envoie un SMS au patient si son numéro est exploitable.
     *
     * Le message est rendu ici, à partir du modèle de texte de
     * l'établissement, puis remis au contrat d'envoi. Un modèle absent ou
     * désactivé n'est pas une erreur bloquante : la notification métier a
     * déjà eu lieu, seul le SMS est omis.
     *
     * @param  array<string, string|int|null>  $variables
     * @return bool  Le message a-t-il été remis à l'infrastructure d'envoi ?
     */
    private function sendPatientSms(
        Patient $patient,
        string $templateKey,
        array $variables,
        ?object $context = null,
        ?Carbon $scheduledFor = null,
    ): bool {
        if (! PhoneNumber::isSendable($patient->phone)) {
            return false;
        }

        $template = SmsTemplate::query()
            ->where('key', $templateKey)
            ->where('is_active', true)
            ->first();

        if ($template === null) {
            return false;
        }

        $envelope = SmsContext::for(
            patient: $patient,
            subject: $context,
            templateKey: $templateKey,
            sendAt: $scheduledFor,
        );

        try {
            $this->sms->dispatch(
                (string) $patient->phone,
                $template->render($variables),
                $envelope->toContextString(),
            );
        } catch (\Throwable $exception) {
            // Un SMS manqué ne doit jamais interrompre un acte médical.
            Log::warning('Envoi SMS impossible', [
                'patient_id' => $patient->getKey(),
                'modele' => $templateKey,
                'erreur' => $exception->getMessage(),
            ]);

            return false;
        }

        return true;
    }
}
