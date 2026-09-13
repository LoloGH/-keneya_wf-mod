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
        self::projectCompanions($patient, $dossier);

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
        self::projectCompanions($patient, $dossier);

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
     * Les accompagnateurs de l'accueil, portes au dossier medical comme
     * personnes a prevenir.
     *
     * Les deux notions n'en font qu'une du point de vue du patient : la
     * personne venue avec lui est celle qu'on appellera s'il faut joindre
     * quelqu'un. L'accueil la releve deja ; la ressaisir dans le dossier
     * medical etait un doublon de travail, et le champ restait vide dans les
     * faits.
     *
     * Additif, jamais destructeur : un contact saisi cote DME et inconnu de
     * l'accueil n'est pas efface. Le rapprochement se fait sur le nom, faute
     * de mieux — un accompagnateur n'a pas d'identifiant — et un lien de
     * parente ou un numero connus du seul dossier medical ne sont pas ecrases
     * par le vide de WorkFlow.
     */
    public static function projectCompanions(Patient $patient, ?DossierMedical $dossier = null): void
    {
        $dossier ??= self::find($patient);

        if ($dossier === null) {
            return;
        }

        $accompagnateurs = $patient->companions()->orderBy('id')->get();

        // Le cas courant : aucun accompagnateur. La projection est appelee a
        // chaque acte clinique, et il n'y a pas lieu d'interroger les contacts
        // du dossier pour n'y rien ecrire.
        if ($accompagnateurs->isEmpty()) {
            return;
        }

        $aUnPrincipal = $dossier->emergencyContacts()->where('is_primary', true)->exists();

        foreach ($accompagnateurs as $accompagnateur) {
            if (blank($accompagnateur->name)) {
                continue;
            }

            $contact = $dossier->emergencyContacts()->firstOrNew(['name' => $accompagnateur->name]);

            if (filled($accompagnateur->relation)) {
                $contact->relationship = $accompagnateur->relation;
            }

            if (filled($accompagnateur->phone)) {
                $contact->phone = $accompagnateur->phone;
            }

            // Le premier accompagnateur devient la personne a prevenir par
            // defaut, a condition que le dossier n'en designe pas deja une :
            // un choix fait dans le dossier medical prime sur l'ordre de
            // saisie a l'accueil.
            if (! $contact->exists && ! $aUnPrincipal) {
                $contact->is_primary = true;
                $aUnPrincipal = true;
            }

            $contact->save();
        }
    }

    /**
     * Les champs que WorkFlow possede, dans la forme attendue par le module.
     *
     * Nom et prenom partent separement depuis la v3.3.2 : l'accueil les saisit
     * en deux champs, comme le dossier medical les tient. Auparavant le nom
     * partait entier dans `last_name` avec un `first_name` vide, faute de
     * mieux — le dossier, l'ordonnance imprimee et chaque document portaient
     * alors une identite d'un seul bloc, dans une forme que personne n'avait
     * saisie.
     *
     * @return array<string, mixed>
     */
    public static function attributes(Patient $patient): array
    {
        return [
            'last_name' => $patient->last_name,
            'first_name' => (string) $patient->first_name,
            'sex' => $patient->gender,
            'age' => $patient->age,
            'phone' => $patient->mobile,
            // Le dossier medical tient desormais la carte dans une colonne a
            // lui, en plus du lien d'identifiant externe ci-dessus : c'est la
            // seule forme qu'un formulaire peut afficher et corriger.
            'id_card_number' => $patient->id_card_number,
            'label' => 'Dossier KEneYa WorkFlow',
        ];
    }
}
