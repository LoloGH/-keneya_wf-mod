<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index sur les rattachements croisés déclarés avant l'existence de leur
 * table cible (consultation ↔ rendez-vous, constantes ↔ hospitalisation).
 *
 * Ces colonnes restent volontairement sans contrainte de clé étrangère :
 * SQLite n'autorise pas l'ajout d'une contrainte sur une table existante,
 * et l'application doit rester portable SQLite / MySQL / PostgreSQL. Les
 * relations sont déclarées côté Eloquent et couvertes par les tests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dme_consultations', function (Blueprint $table) {
            $table->index('appointment_id');
            $table->index('hospitalization_id');
        });

        Schema::table('dme_vital_signs', function (Blueprint $table) {
            $table->index('hospitalization_id');
        });
    }

    public function down(): void
    {
        Schema::table('dme_consultations', function (Blueprint $table) {
            $table->dropIndex(['appointment_id']);
            $table->dropIndex(['hospitalization_id']);
        });

        Schema::table('dme_vital_signs', function (Blueprint $table) {
            $table->dropIndex(['hospitalization_id']);
        });
    }
};
