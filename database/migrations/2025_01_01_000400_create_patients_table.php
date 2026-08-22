<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('patient_code', 20)->unique(); // ex. HFD-00001, genere automatiquement
            $table->string('name');
            $table->unsignedTinyInteger('age');
            $table->enum('gender', ['Homme', 'Femme']);
            $table->string('mobile');
            $table->string('crno', 20)->nullable(); // numero de dossier papier, saisi manuellement
            $table->foreignId('service_id')->constrained();
            $table->unsignedInteger('token');
            $table->enum('status', ['waiting', 'called'])->default('waiting');
            $table->timestamps();

            $table->index(['service_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
