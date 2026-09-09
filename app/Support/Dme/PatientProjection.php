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
        $dossier = app(PatientIdentifierResolver::class)->resolve(
            system: self::SYSTEM,
            value: (string) $patient->patient_code,
            attributes: self::attributes($patient),
        );

        self::linkIdCard($patient, $dossier);

        return $dossier;
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

    /** Le systeme sous lequel la carte d'identite se presente au DME. */
    public const SYSTEM_CARTE = 'carte_identite';

    /**
     * Repercute au dossier medical ce que l'accueil vient de corriger.
     *
     * Ne cree rien : un patient sans dossier medical n'en obtient pas un pour
     * une correction de nom. Le dossier nait au premier acte clinique, jamais
     * a l'accueil.
     *
     * Le sens reste unique. WorkFlow n'ecrit que les champs qu'il possede, et
     * ne touche a rien de clinique : ni groupe sanguin, ni antecedent, ni
     * medecin traitant.
     */
    public static function sync(Patient $patient): ?DossierMedical
    {
        $dossier = self::find($patient);

        if ($dossier === null) {
            return null;
        }

        // Par le module, jamais en ecrivant directement : c'est lui qui
        // traduit « Homme » vers la valeur que sa colonne accepte, et qui sait
        // ce qu'il ne faut pas ecraser.
        app(PatientIdentifierResolver::class)->sync($dossier, self::attributes($patient));

        self::linkIdCard($patient, $dossier);

        return $dossier;
    }

    /**
     * Relie la carte d'identite au dossier medical, dans la table des
     * identifiants externes du module.
     *
     * C'est ce qui permet au DME de reconnaitre la meme personne au-dela du
     * numero de dossier WorkFlow, et a un futur rapprochement de dossiers de
     * s'appuyer sur autre chose qu'un nom.
     *
     * Une carte effacee retire le lien : un identifiant qui ne correspond plus
     * a rien vaut moins que pas d'identifiant.
     */
    public static function linkIdCard(Patient $patient, ?DossierMedical $dossier = null): void
    {
        $dossier ??= self::find($patient);

        if ($dossier === null) {
            return;
        }

        $identifiants = $dossier->identifiers();

        if (blank($patient->id_card_number)) {
            $identifiants->where('system', self::SYSTEM_CARTE)->delete();

            return;
        }

        $identifiants->updateOrCreate(
            ['system' => self::SYSTEM_CARTE],
            ['value' => (string) $patient->id_card_number, 'label' => 'Carte d\'identite'],
        );
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
