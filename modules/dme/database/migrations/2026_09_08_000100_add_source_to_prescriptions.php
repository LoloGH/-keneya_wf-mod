<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provenance d'une ordonnance venue d'une application hôte.
 *
 * Une application qui monte le module peut avoir ses propres ordonnances, et
 * vouloir les reprendre dans le dossier médical. Sans marque de provenance,
 * rejouer la reprise crée des doublons : en silence, et sur des ordonnances,
 * ce qui est le pire des endroits.
 *
 * Le couple (système, identifiant) est unique : une même ordonnance de l'hôte
 * ne peut donc être reprise qu'une seule fois, quel que soit le nombre de
 * passages du traitement par lots. C'est le même principe que
 * `patient_identifiers`, appliqué à un enregistrement plutôt qu'à une identité.
 *
 * Les deux colonnes restent nulles pour une ordonnance née dans le DME, ce qui
 * est le cas courant : elles ne décrivent que ce qui vient d'ailleurs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dme_prescriptions', function (Blueprint $table) {
            $table->string('source_system')->nullable()->after('prescription_number');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_system');

            $table->unique(['source_system', 'source_id'], 'dme_prescriptions_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('dme_prescriptions', function (Blueprint $table) {
            $table->dropUnique('dme_prescriptions_source_unique');
            $table->dropColumn(['source_system', 'source_id']);
        });
    }
};
