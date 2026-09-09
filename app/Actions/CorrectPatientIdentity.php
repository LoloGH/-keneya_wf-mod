<?php

namespace App\Actions;

use App\Models\Patient;
use App\Support\Audit;
use App\Support\Dme\PatientProjection;
use Illuminate\Support\Facades\DB;

/**
 * Correction de l'identite d'un patient, a l'accueil (v3.3.1).
 *
 * Un nom mal orthographie, un numero qui a change, une profession qui evolue :
 * l'accueil doit pouvoir corriger, sinon la seule issue est d'ouvrir un second
 * dossier pour la meme personne, ce que tout le reste du produit s'emploie a
 * empecher.
 *
 * Cinq champs, et cinq seulement : nom, telephone, profession, sexe, numero de
 * carte d'identite. Ce sont ceux que WorkFlow possede et que l'accueil peut
 * constater. Tout le reste est hors de portee :
 *
 *  - **le `patient_code` ne change jamais.** C'est la promesse centrale du
 *    produit, un identifiant unique et permanent. Corriger un dossier n'est
 *    pas en creer un second ;
 *  - l'age n'est pas corrigible ici : le dossier medical tient une date de
 *    naissance, et projeter un age approche par-dessus une date d'etat civil
 *    l'ecraserait ;
 *  - rien de clinique. L'accueil ne touche pas au groupe sanguin, aux
 *    antecedents ni au medecin traitant.
 *
 * La correction se projette dans le dossier medical, dans le sens unique du
 * chantier : WorkFlow ecrit vers le DME, jamais l'inverse. Un nom corrige a
 * l'accueil se lit corrige sur l'ordonnance imprimee.
 */
class CorrectPatientIdentity
{
    /**
     * Les champs corrigibles, et eux seuls. La liste vit ici plutot que dans
     * le formulaire : c'est une regle metier, pas une question d'ecran.
     *
     * @var array<string, string>
     */
    public const CHAMPS = [
        'name' => 'nom',
        'mobile' => 'telephone',
        'profession' => 'profession',
        'gender' => 'sexe',
        'id_card_number' => 'numero de la carte d\'identite',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Patient $patient, array $data): Patient
    {
        $retenus = array_intersect_key($data, self::CHAMPS);

        // Une chaine vide vaut absence sur les champs facultatifs : sans cela,
        // un champ efface laisserait une chaine vide que la recherche de
        // doublon rapprocherait d'un autre dossier tout aussi vide.
        foreach (['profession', 'id_card_number'] as $facultatif) {
            if (array_key_exists($facultatif, $retenus)) {
                $retenus[$facultatif] = filled($retenus[$facultatif]) ? $retenus[$facultatif] : null;
            }
        }

        $avant = $patient->only(array_keys($retenus));

        DB::transaction(function () use ($patient, $retenus): void {
            $patient->update($retenus);

            // La projection suit : le dossier medical porte le nom, le sexe et
            // le telephone que l'accueil vient de corriger.
            PatientProjection::sync($patient->fresh());
        });

        $change = $this->difference($avant, $patient->fresh()->only(array_keys($retenus)));

        // Rien n'a bouge : pas de ligne au journal. Un audit qui consigne des
        // corrections vides devient illisible.
        if ($change !== []) {
            Audit::log(
                Audit::EVENT_PATIENT_UPDATED,
                sprintf(
                    'Identite du dossier %s corrigee : %s.',
                    $patient->patient_code,
                    implode(', ', $change),
                ),
                $patient,
            );
        }

        return $patient->fresh();
    }

    /**
     * Ce qui a change, dit en clair : « nom : Fode Drame -> Fode Drame ».
     *
     * @param  array<string, mixed>  $avant
     * @param  array<string, mixed>  $apres
     * @return array<int, string>
     */
    private function difference(array $avant, array $apres): array
    {
        $change = [];

        foreach ($apres as $champ => $valeur) {
            if (($avant[$champ] ?? null) === $valeur) {
                continue;
            }

            $change[] = sprintf(
                '%s : %s -> %s',
                self::CHAMPS[$champ] ?? $champ,
                $avant[$champ] ?: 'vide',
                $valeur ?: 'vide',
            );
        }

        return $change;
    }
}
