<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Antécédents, allergies, pathologies chroniques et traitements habituels.
 *
 * Correspondances FHIR visées (§44) :
 *   allergies           -> AllergyIntolerance
 *   chronic_conditions  -> Condition
 *   medications         -> MedicationStatement
 */
return new class extends Migration
{
    public function up(): void
    {
        // §16, antécédents personnels, chirurgicaux, familiaux,
        // gynéco-obstétriques et facteurs de risque, dans une table unique
        // discriminée par `category` afin de garder l'historique homogène.
        Schema::create('dme_medical_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('dme_patients')->cascadeOnDelete();
            $table->enum('category', [
                'personal', 'surgical', 'family', 'gynecological', 'risk_factor',
            ]);
            $table->string('label');
            $table->string('code')->nullable();          // CIM-10 lorsque disponible
            $table->string('code_system')->nullable();   // icd10, snomed...
            $table->string('year')->nullable();
            $table->date('occurred_on')->nullable();
            $table->string('facility')->nullable();      // antécédents chirurgicaux
            $table->string('relative')->nullable();      // antécédents familiaux
            $table->text('complications')->nullable();
            $table->text('comment')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['patient_id', 'category']);
        });

        Schema::create('dme_allergies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('dme_patients')->cascadeOnDelete();
            $table->string('allergen');
            $table->string('allergen_type')->nullable();  // medication, food, environment, other
            $table->string('reaction')->nullable();
            $table->enum('severity', ['mild', 'moderate', 'severe', 'unknown'])->default('unknown');
            $table->date('observed_on')->nullable();
            $table->enum('status', ['active', 'resolved', 'refuted'])->default('active');
            $table->text('comment')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Le contrôle d'allergie à la prescription (§22) lit cet index.
            $table->index(['patient_id', 'status']);
            $table->index('severity');
        });

        Schema::create('dme_chronic_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('dme_patients')->cascadeOnDelete();
            $table->string('label');
            $table->string('code')->nullable();
            $table->string('code_system')->nullable();
            $table->date('diagnosed_on')->nullable();
            $table->enum('status', ['active', 'controlled', 'resolved'])->default('active');
            $table->text('comment')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['patient_id', 'status']);
        });

        // §18, traitements habituels (hors ordonnance ponctuelle)
        Schema::create('dme_medications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('dme_patients')->cascadeOnDelete();
            $table->string('name');
            $table->string('dosage')->nullable();
            $table->string('frequency')->nullable();
            $table->string('route')->nullable();   // orale, IV, IM, cutanée...
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->foreignId('prescriber_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['active', 'suspended', 'stopped'])->default('active');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dme_medications');
        Schema::dropIfExists('dme_chronic_conditions');
        Schema::dropIfExists('dme_allergies');
        Schema::dropIfExists('dme_medical_histories');
    }
};
