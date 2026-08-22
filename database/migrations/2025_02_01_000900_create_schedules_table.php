<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Emploi du temps du personnel (addendum v2, point 8). Gere par l'admin,
 * consulte en lecture seule par chacun dans sa propre interface.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained(); // medecin ou receptionniste
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignId('service_id')->nullable()->constrained(); // pertinent pour un medecin
            $table->timestamps();

            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
