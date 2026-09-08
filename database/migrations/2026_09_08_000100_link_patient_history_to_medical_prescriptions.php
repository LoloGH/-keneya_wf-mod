<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'ordonnance que designe une ligne d'historique (v3.3.1).
 *
 * Depuis que l'ordonnance vit dans le dossier medical, la frise du parcours ne
 * peut plus la retrouver comme avant. Elle appariait une entree « ordonnance »
 * avec la n-ieme ordonnance du meme passage — un rapprochement par rang, que
 * son propre commentaire signalait comme un pis-aller assume : « la consigne
 * etait de corriger l'affichage, pas le schema ».
 *
 * Le schema change maintenant, et la correction devient la bonne : la ligne
 * d'historique designe l'ordonnance, une fois pour toutes.
 *
 * Aucune cle etrangere : la table visee appartient au module, qui peut ne pas
 * etre monte. Une reference nulle ou orpheline se lit comme « pas
 * d'ordonnance », ce que la frise sait deja afficher.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_history', function (Blueprint $table) {
            $table->unsignedBigInteger('dme_prescription_id')->nullable()->after('referral_id');
            $table->index('dme_prescription_id');
        });
    }

    public function down(): void
    {
        Schema::table('patient_history', function (Blueprint $table) {
            $table->dropIndex(['dme_prescription_id']);
            $table->dropColumn('dme_prescription_id');
        });
    }
};
