<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tentatives de saisie du code d'acces au portail patient (v3.2, point 7).
 *
 * Le lien ne perime jamais et un code a quatre chiffres n'offre que 10 000
 * combinaisons : le verrouillage temporaire apres cinq echecs est la seconde
 * couche de protection, apres l'UUID non devinable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_access_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained();
            $table->unsignedTinyInteger('failures')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();

            $table->unique('patient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_access_attempts');
    }
};
