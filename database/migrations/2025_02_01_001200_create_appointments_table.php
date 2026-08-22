<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rendez-vous (addendum v2, point 9).
 *
 * `no_show` distingue le patient qui ne s'est pas presente d'une annulation
 * volontaire (addendum v3) : les deux ne disent pas la meme chose dans les
 * statistiques.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('doctor_id')->constrained();
            $table->foreignId('service_id')->constrained();
            $table->dateTime('scheduled_at');
            $table->enum('status', ['scheduled', 'checked_in', 'no_show', 'cancelled'])->default('scheduled');
            // Visite ouverte quand le patient se presente (« Orienter le patient »).
            $table->foreignId('visit_id')->nullable()->constrained();
            $table->timestamps();

            $table->index(['scheduled_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
