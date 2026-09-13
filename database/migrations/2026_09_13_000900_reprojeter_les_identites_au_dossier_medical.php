<?php

use App\Models\Patient;
use App\Support\Dme\PatientProjection;
use Illuminate\Database\Migrations\Migration;

/**
 * Reprise : les dossiers medicaux deja ouverts recoivent l'identite corrigee.
 *
 * Les projections anterieures a la v3.3.2 versaient le nom entier de WorkFlow
 * dans `last_name` en laissant `first_name` vide, ou s'en remettaient au
 * decoupage du module, qui prenait le premier mot pour le nom de famille et
 * rendait donc « Aminata Traore » en « Traore Aminata » partout ou le dossier
 * affiche un nom. Les dossiers deja ouverts porteraient cette identite
 * indefiniment : rien ne les rouvre, la projection ne repassant qu'a la
 * correction d'une identite a l'accueil.
 *
 * La meme passe porte au dossier ce que la v3.3.2 y ajoute : le numero de la
 * carte d'identite dans sa colonne, et les accompagnateurs releves a l'accueil
 * comme personnes a prevenir. Sans elle, ces deux apports ne vaudraient que
 * pour les patients enregistres apres la mise a jour.
 *
 * `sync()` ne cree aucun dossier et ne touche a rien de clinique : un patient
 * sans dossier medical n'en obtient pas un ici. Le sens reste unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Patient::query()->orderBy('id')->chunkById(200, function ($patients): void {
            foreach ($patients as $patient) {
                PatientProjection::sync($patient);
            }
        });
    }

    /**
     * Rien a defaire : reecrire les anciennes identites approximatives
     * par-dessus les corrections faites depuis serait une perte, pas un retour en
     * arriere.
     */
    public function down(): void {}
};
