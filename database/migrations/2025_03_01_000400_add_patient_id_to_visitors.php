<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un visiteur rend visite a quelqu'un (v3.2, point 1).
 *
 * `nullable` a dessein : une visite a l'administration pour une demarche n'a
 * pas de patient. Le formulaire impose toutefois le champ des qu'un service
 * clinique est choisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->foreignId('patient_id')->nullable()->after('id')->constrained();
        });
    }

    public function down(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->dropForeign(['patient_id']);
            $table->dropColumn('patient_id');
        });
    }
};
