<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hospitalisation, événements de séjour et soins infirmiers.
 *
 * Correspondance FHIR visée : Encounter (classe « inpatient ») (§44).
 *
 * `hospitalization_events` matérialise la timeline du séjour demandée
 * en §25 (admission -> observations -> soins -> examens -> traitement ->
 * évolution -> sortie) sans dupliquer les données cliniques : chaque
 * événement peut référencer l'enregistrement d'origine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dme_hospitalizations', function (Blueprint $table) {
            $table->id();
            $table->string('hospitalization_number')->unique(); // HOSP-2026-000001
            $table->foreignId('patient_id')->constrained('dme_patients')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('dme_services')->nullOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();

            // Admission
            $table->dateTime('admitted_at');
            $table->text('admission_reason')->nullable();
            $table->string('admission_diagnosis')->nullable();
            $table->string('room')->nullable();
            $table->string('bed')->nullable();

            // Sortie
            $table->dateTime('discharged_at')->nullable();
            $table->string('discharge_diagnosis')->nullable();
            $table->longText('discharge_treatment')->nullable();
            $table->longText('discharge_recommendations')->nullable();
            $table->longText('discharge_summary')->nullable();
            $table->enum('discharge_type', ['home', 'transfer', 'against_advice', 'deceased'])->nullable();

            $table->enum('status', ['admitted', 'discharged', 'transferred', 'cancelled'])
                ->default('admitted');
            $table->timestamps();
            $table->softDeletes();

            $table->index('patient_id');
            $table->index('status');
            $table->index('admitted_at');
        });

        Schema::create('dme_hospitalization_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospitalization_id')->constrained('dme_hospitalizations')->cascadeOnDelete();
            $table->enum('type', [
                'admission', 'observation', 'care', 'exam', 'treatment',
                'evolution', 'transfer', 'discharge',
            ]);
            $table->dateTime('occurred_at');
            $table->string('title');
            $table->longText('content')->nullable();

            // Référence facultative vers l'enregistrement clinique d'origine
            $table->nullableMorphs('source');

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hospitalization_id', 'occurred_at']);
        });

        // §26, soins infirmiers
        Schema::create('dme_nursing_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('dme_patients')->cascadeOnDelete();
            $table->foreignId('hospitalization_id')->nullable()->constrained('dme_hospitalizations')->nullOnDelete();
            $table->enum('type', [
                'care', 'medication_administration', 'observation', 'incident', 'handover',
            ])->default('care');
            $table->dateTime('occurred_at');
            $table->string('title');
            $table->longText('content')->nullable();
            $table->string('medication_name')->nullable();
            $table->string('medication_dose')->nullable();
            $table->string('medication_route')->nullable();
            $table->enum('severity', ['info', 'warning', 'critical'])->default('info');
            $table->foreignId('nurse_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['patient_id', 'occurred_at']);
            $table->index('hospitalization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dme_nursing_notes');
        Schema::dropIfExists('dme_hospitalization_events');
        Schema::dropIfExists('dme_hospitalizations');
    }
};
