<?php

namespace App\Support;

use App\Models\Doctor;
use App\Models\Prescription;
use App\Models\Setting;

/**
 * Les donnees du gabarit d'ordonnance, en un seul endroit (v3.2.9, point 2).
 *
 * Deux controleurs rendent ce PDF — celui du medecin et celui du portail
 * patient. Sans point commun, l'un aurait fini par afficher un en-tete que
 * l'autre ignore, et le patient n'aurait pas vu la meme ordonnance que son
 * medecin.
 */
final class PrescriptionPdfData
{
    /**
     * @return array<string, mixed>
     */
    public static function for(Prescription $prescription): array
    {
        $medecin = $prescription->doctor;

        return [
            'prescription' => $prescription,
            'productName' => config('keneya.name'),

            // En-tete : tout vient de `settings`, rien du gabarit.
            'hospitalName' => hospital_name(),
            'hospitalAddress' => Setting::get(Setting::HOSPITAL_ADDRESS),
            'hospitalPhone' => Setting::get(Setting::HOSPITAL_PHONE),
            'hospitalEmail' => Setting::get(Setting::HOSPITAL_EMAIL),

            // Signature et tampons : des chemins absolus, ou null. C'est le
            // modele qui verifie que le fichier est bien la — un chemin mort
            // ferait echouer dompdf, et une ordonnance qu'on ne peut plus
            // imprimer serait pire qu'une signature absente.
            'hospitalStamp' => Doctor::fichierExistant(Setting::get(Setting::HOSPITAL_STAMP_PATH)),
            'doctorSignature' => $medecin?->signatureFile(),
            'doctorStamp' => $medecin?->stampFile(),
        ];
    }
}
