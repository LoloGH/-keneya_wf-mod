<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le numero de la carte d'identite, a l'accueil (v3.3.1).
 *
 * C'est le seul champ qui distingue deux personnes a coup sur. Le telephone
 * change et se prete, le nom s'ecrit de dix facons, l'age se donne a un an
 * pres : la recherche de doublon travaillait jusqu'ici sur des indices, et
 * laissait passer la meme personne sous deux dossiers.
 *
 * Facultatif, et il doit le rester : tout le monde n'a pas sa carte sur soi,
 * et un patient qui arrive aux urgences sans papiers doit etre enregistre
 * quand meme. Recommande, jamais exige.
 *
 * Indexe mais **pas unique**. A l'accueil, un numero deja connu ne doit pas
 * faire echouer l'enregistrement : il doit faire apparaitre le dossier
 * existant et laisser la receptionniste decider s'il s'agit de la meme
 * personne. Une contrainte d'unicite transformerait cette aide en blocage,
 * devant un patient qui attend.
 *
 * Le visiteur le recoit aussi : un accompagnateur revient, et l'etablissement
 * doit pouvoir le reconnaitre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('id_card_number', 60)->nullable()->after('mobile');
            $table->index('id_card_number');
        });

        Schema::table('visitors', function (Blueprint $table) {
            $table->string('id_card_number', 60)->nullable()->after('mobile');
            $table->index('id_card_number');
        });
    }

    public function down(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->dropIndex(['id_card_number']);
            $table->dropColumn('id_card_number');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex(['id_card_number']);
            $table->dropColumn('id_card_number');
        });
    }
};
