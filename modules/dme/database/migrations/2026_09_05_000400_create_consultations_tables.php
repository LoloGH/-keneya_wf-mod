<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consultations, constantes vitales, examen clinique et diagnostics.
 *
 * Correspondances FHIR visées (§44) :
 *   consultations -> Encounter
 *   vital_signs   -> Observation
 *   diagnoses     -> Condition
 *
 * Historisation (§40) : les constantes ne sont jamais écrasées. Chaque
 * relevé crée une ligne datée et signée par son auteur, ce qui permet les
 * courbes d'évolution (poids, tension, glycémie...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dme_consultations', function (Blueprint $table) {
            $table->id();
            $table->string('consultation_number')->unique(); // CONS-2026-000001
            $table->foreignId('patient_id')->constrained('dme_patients')->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('dme_services')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable();
            $table->foreignId('hospitalization_id')->nullable();

            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->string('type')->default('ambulatory'); // ambulatory, emergency, follow_up, inpatient
            $table->enum('status', ['draft', 'in_progress', 'completed', 'cancelled'])->default('draft');

            $table->text('reason')->nullable();            // motif de consultation
            $table->longText('history_of_illness')->nullable(); // histoire de la maladie
            $table->longText('treatment_plan')->nullable();
            $table->longText('follow_up')->nullable();
            $table->text('recommendations')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Index (§59)
            $table->index('patient_id');
            $table->index('doctor_id');
            $table->index('started_at');
            $table->index('status');
            $table->index(['patient_id', 'started_at']);
        });

        Schema::create('dme_vital_signs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('dme_patients')->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('dme_consultations')->nullOnDelete();
            $table->foreignId('hospitalization_id')->nullable();
            $table->dateTime('measured_at');

            $table->decimal('temperature', 4, 1)->nullable();          // °C
            $table->unsignedSmallInteger('systolic')->nullable();      // mmHg
            $table->unsignedSmallInteger('diastolic')->nullable();     // mmHg
            $table->unsignedSmallInteger('heart_rate')->nullable();    // bpm
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->unsignedSmallInteger('oxygen_saturation')->nullable(); // %
            $table->decimal('weight', 5, 2)->nullable();               // kg
            $table->decimal('height', 5, 2)->nullable();               // cm
            $table->decimal('bmi', 5, 2)->nullable();                  // calculé
            $table->decimal('glycemia', 5, 2)->nullable();             // g/L
            $table->unsignedTinyInteger('pain_scale')->nullable();     // 0-10
            $table->text('comment')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['patient_id', 'measured_at']);
            $table->index('consultation_id');
        });

        // §19, examen clinique par appareil
        Schema::create('dme_clinical_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained('dme_consultations')->cascadeOnDelete();
            $table->string('system'); // general, cardiovascular, respiratory, abdominal,
                                      // neurological, ent, dermatological, other
            $table->longText('content');
            $table->boolean('is_abnormal')->default(false);
            $table->timestamps();

            $table->index(['consultation_id', 'system']);
        });

        Schema::create('dme_diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('dme_patients')->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('dme_consultations')->nullOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('label');
            $table->string('code')->nullable();                        // CIM-10 / ICD-10 (§19)
            $table->string('code_system')->default('icd10');
            $table->enum('type', ['primary', 'secondary', 'differential'])->default('primary');
            $table->enum('status', ['suspected', 'confirmed', 'chronic', 'resolved'])->default('suspected');
            $table->date('diagnosed_on')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'status']);
            $table->index('consultation_id');
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dme_diagnoses');
        Schema::dropIfExists('dme_clinical_notes');
        Schema::dropIfExists('dme_vital_signs');
        Schema::dropIfExists('dme_consultations');
    }
};
