<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Noyau patient du dossier médical électronique.
 *
 * Correspondance FHIR visée : Patient (§44).
 *
 * Point d'attention phase 2 (§62) : `patient_number` est l'identifiant
 * métier stable et `patient_identifiers` accueille les identifiants
 * externes (registre national, Keneya Workflow, INS...). Le raccordement
 * futur au patient unique de Keneya Workflow se fera par cette table
 * d'identifiants, sans modifier les données cliniques.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dme_patients', function (Blueprint $table) {
            $table->id();
            $table->string('patient_number')->unique(); // PAT-2026-000001

            // Identité
            $table->string('last_name');
            $table->string('first_name');
            $table->enum('sex', ['male', 'female', 'other', 'unknown'])->default('unknown');
            $table->date('birth_date')->nullable();
            $table->boolean('birth_date_estimated')->default(false);
            $table->string('birth_place')->nullable();
            $table->string('nationality')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('occupation')->nullable();
            $table->string('photo_path')->nullable();

            // Coordonnées
            $table->string('phone')->nullable();
            $table->string('phone_secondary')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();

            // Informations médicales de premier niveau
            $table->string('blood_group')->nullable(); // A+, O-, ...
            $table->foreignId('attending_doctor_id')->nullable()->constrained('users')->nullOnDelete();

            // Cycle de vie du dossier
            $table->enum('status', ['active', 'inactive', 'deceased', 'archived'])->default('active');
            $table->date('deceased_at')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Index de recherche (§59)
            $table->index('patient_number');
            $table->index('phone');
            $table->index('last_name');
            $table->index('birth_date');
            $table->index('status');
            $table->index(['last_name', 'first_name']);
        });

        Schema::create('dme_patient_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('dme_patients')->cascadeOnDelete();
            $table->string('system');  // ex : keneya_workflow, national_registry, insurance
            $table->string('value');
            $table->string('label')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['system', 'value']);
            $table->index('patient_id');
        });

        Schema::create('dme_emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('dme_patients')->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship')->nullable();
            $table->string('phone');
            $table->string('phone_secondary')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index('patient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dme_emergency_contacts');
        Schema::dropIfExists('dme_patient_identifiers');
        Schema::dropIfExists('dme_patients');
    }
};
