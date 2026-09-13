<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le numéro de la carte d'identité au dossier médical.
 *
 * Le dossier tenait déjà cette information, mais seulement comme identifiant
 * externe rattaché par l'hôte : rien ne l'affichait, et personne ne pouvait la
 * saisir depuis le module. Une réceptionniste relevait la carte à l'accueil,
 * un médecin ouvrait le dossier, et la pièce d'identité n'y figurait pas.
 *
 * Facultatif, et il doit le rester : tout le monde n'a pas sa carte sur soi,
 * et un patient arrivé aux urgences sans papiers doit avoir un dossier quand
 * même. Recommandé, jamais exigé.
 *
 * Indexé mais **pas unique** : deux dossiers portant le même numéro sont un
 * doublon à examiner, pas une erreur de saisie à rejeter au moment où
 * quelqu'un attend.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dme_patients', function (Blueprint $table) {
            $table->string('id_card_number', 60)->nullable()->after('occupation');
            $table->index('id_card_number');
        });
    }

    public function down(): void
    {
        Schema::table('dme_patients', function (Blueprint $table) {
            $table->dropIndex(['id_card_number']);
            $table->dropColumn('id_card_number');
        });
    }
};
