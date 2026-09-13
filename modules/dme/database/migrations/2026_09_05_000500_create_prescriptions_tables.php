<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ordonnances et lignes de prescription.
 *
 * Correspondance FHIR visée : MedicationRequest (§44).
 *
 * `allergy_warnings` conserve la trace du contrôle d'allergie effectué au
 * moment de la validation (§22) : l'application avertit mais ne supprime
 * jamais une ligne, la décision reste au prescripteur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dme_prescriptions', function (Blueprint $table) {
            $table->id();
            $table->string('prescription_number')->unique(); // ORD-2026-000001
            $table->foreignId('patient_id')->constrained('dme_patients')->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('dme_consultations')->nullOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->date('issued_on');
            $table->date('valid_until')->nullable();
            $table->enum('status', ['draft', 'validated', 'dispensed', 'cancelled'])->default('draft');
            $table->text('instructions')->nullable();

            // Trace du contrôle d'allergie au moment de la validation
            $table->json('allergy_warnings')->nullable();
            $table->boolean('allergy_warning_acknowledged')->default(false);
            $table->text('allergy_warning_justification')->nullable();

            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('validated_at')->nullable();
            $table->foreignId('dispensed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('dispensed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('patient_id');       // §59
            $table->index('doctor_id');
            $table->index('status');
            $table->index('issued_on');
        });

        Schema::create('dme_prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained('dme_prescriptions')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);

            $table->string('medication_name');
            $table->string('dosage')->nullable();       // 500 mg
            $table->string('form')->nullable();         // comprimé, sirop, injectable...
            $table->string('route')->nullable();        // orale, IV, IM...
            $table->string('frequency')->nullable();    // 2 fois par jour
            $table->string('duration')->nullable();     // 7 jours
            $table->string('quantity')->nullable();     // 14 comprimés
            $table->text('instructions')->nullable();
            $table->boolean('is_substitutable')->default(true);

            $table->timestamps();

            $table->index('prescription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dme_prescription_items');
        Schema::dropIfExists('dme_prescriptions');
    }
};
