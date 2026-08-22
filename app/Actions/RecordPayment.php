<?php

namespace App\Actions;

use App\Models\Payment;
use App\Models\User;
use App\Models\Visit;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Enregistrement d'un encaissement (addendum v2, point 9).
 *
 * La caisse n'est pas un service au sens de la file d'attente : c'est une
 * section de l'accueil (ticket de consultation) et du service (acte realise).
 */
class RecordPayment
{
    public function execute(
        Visit $visit,
        User $recordedBy,
        string $type,
        int $amount,
        ?int $serviceId = null,
        bool $paid = true,
    ): Payment {
        if (! in_array($type, [Payment::TYPE_TICKET, Payment::TYPE_SERVICE], true)) {
            throw new InvalidArgumentException('Type d\'encaissement inconnu.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('Le montant doit etre superieur a zero.');
        }

        $payment = DB::transaction(fn (): Payment => Payment::create([
            'patient_id' => $visit->patient_id,
            'visit_id' => $visit->getKey(),
            'type' => $type,
            'service_id' => $serviceId ?? ($type === Payment::TYPE_SERVICE ? $visit->service_id : null),
            'amount' => $amount,
            'status' => $paid ? Payment::STATUS_PAID : Payment::STATUS_PENDING,
            'recorded_by_user_id' => $recordedBy->getKey(),
        ]));

        // Journalise seulement une fois la transaction validee.
        Audit::log(
            Audit::EVENT_PAYMENT_RECORDED,
            sprintf('Encaissement de %s FCFA (%s).', number_format($amount, 0, ',', ' '), $type),
            $payment,
            ['montant' => $amount, 'type' => $type],
        );

        return $payment;
    }

    public function markPaid(Payment $payment): Payment
    {
        if ($payment->status === Payment::STATUS_PAID) {
            throw new InvalidArgumentException('Cet encaissement est deja marque comme paye.');
        }

        $payment->update(['status' => Payment::STATUS_PAID]);

        return $payment;
    }
}
