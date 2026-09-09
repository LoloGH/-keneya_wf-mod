<?php

namespace App\Support\Dme;

use App\Models\Patient;
use Keneya\Dme\Models\Patient as DossierMedical;
use Keneya\Dme\Patients\PatientIdentifierResolver;

/**
 * La projection d'un patient de WorkFlow vers son dossier medical (v3.3.1).
 *
 * Chaque formulaire du chantier commence par la meme question, quel dossier
 * du DME correspond a ce patient ?, et elle n'admet qu'une reponse : la table
 * d'identifiants externes du module, jamais un rapprochement sur le nom. Cette
 * classe tient cette reponse, et la forme des attributs projetes, en un seul
 * endroit : les formulaires, le bouton « Dossier medical complet », le portail
 * et les commandes de reprise s'en servent tous, et ne peuvent donc pas se
 * mettre a diverger.
 *
 * Le sens est unique, conformement au §1 du chantier : WorkFlow ecrit vers le
 * DME, jamais l'inverse.
 */
final class PatientProjection
{
    /** Le systeme d'identification sous lequel WorkFlow se presente au DME. */
    public const SYSTEM = 'keneya_workflow';

    /**
     * Le dossier medical de ce patient, cree au besoin.
     *
     * Effet de bord voulu : un patient consulte pour la premiere fois obtient
     * son dossier au premier acte, sans que personne ait a y penser.
     */
    public static function resolve(Patient $patient): DossierMedical
    {
        return app(PatientIdentifierResolver::class)->resolve(
            system: self::SYSTEM,
            value: (string) $patient->patient_code,
            attributes: self::attributes($patient),
        );
    }

    /**
     * Le dossier medical de ce patient, sans rien creer. Nul tant qu'aucun
     * acte n'a ete pose : le dossier nait au premier formulaire du DME, jamais
     * a l'enregistrement a l'accueil.
     */
    public static function find(Patient $patient): ?DossierMedical
    {
        return app(PatientIdentifierResolver::class)
            ->find(self::SYSTEM, (string) $patient->patient_code);
    }

    /**
     * Les champs que WorkFlow possede, dans la forme attendue par le module.
     *
     * Le nom part entier dans `last_name`, et `first_name` reste vide. WorkFlow
     * ne tient qu'un seul champ `name`, saisi tel que la personne se presente ;
     * le laisser deviner au module reviendrait a couper au premier espace, ce
     * qui reordonne le nom sur l'ordonnance imprimee et sur chaque document du
     * dossier. Un nom sur une ordonnance doit se lire exactement comme a
     * l'accueil.
     *
     * @return array<string, mixed>
     */
    public static function attributes(Patient $patient): array
    {
        return [
            'last_name' => $patient->name,
            'first_name' => '',
            'sex' => $patient->gender,
            'age' => $patient->age,
            'phone' => $patient->mobile,
            'label' => 'Dossier KEneYa WorkFlow',
        ];
    }
}
