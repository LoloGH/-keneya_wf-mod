<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ordonnances (addendum v2, point 9).
 *
 * Texte libre pour cette version : l'addendum v3 ecarte explicitement une
 * structure medicament/posologie/duree tant que l'usage reel a HFD n'aura pas
 * montre ce qui manque.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('visit_id')->nullable()->constrained();
            $table->foreignId('doctor_id')->constrained();
            $table->text('content');
            $table->timestamps();

            $table->index(['patient_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
