<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La conclusion du biologiste sur l'ensemble de la demande.
 *
 * Les résultats sont saisis paramètre par paramètre, et c'est bien ainsi : une
 * valeur chiffrée avec son unité et ses bornes se compare, se trace, s'exporte.
 * Mais le laboratoire rend aussi une lecture d'ensemble, « hémogramme
 * compatible avec une anémie ferriprive », qui n'appartient à aucune ligne en
 * particulier et n'avait jusqu'ici nulle part où se poser.
 *
 * L'imagerie avait déjà la sienne (`dme_imaging_reports.conclusion`) ; le
 * laboratoire la reçoit ici, sur la demande elle-même plutôt que dans une table
 * séparée : contrairement au compte rendu d'imagerie, elle ne porte ni
 * technique, ni statut de rédaction propre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dme_lab_orders', function (Blueprint $table) {
            $table->text('conclusion')->nullable()->after('indication');
        });
    }

    public function down(): void
    {
        Schema::table('dme_lab_orders', function (Blueprint $table) {
            $table->dropColumn('conclusion');
        });
    }
};
