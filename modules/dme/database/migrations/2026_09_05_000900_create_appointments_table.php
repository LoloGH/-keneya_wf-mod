<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rendez-vous (§27). Correspondance FHIR visée : Appointment (§44).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dme_appointments', function (Blueprint $table) {
            $table->id();
            $table->string('appointment_number')->unique(); // RDV-2026-000001
            $table->foreignId('patient_id')->constrained('dme_patients')->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('dme_services')->nullOnDelete();

            $table->dateTime('scheduled_for');
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->string('reason')->nullable();
            $table->enum('status', ['scheduled', 'confirmed', 'completed', 'cancelled', 'no_show'])
                ->default('scheduled');
            $table->text('notes')->nullable();

            // Rappel SMS, le service SMS reste découplé (§35)
            $table->boolean('reminder_enabled')->default(true);
            $table->dateTime('reminder_sent_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('patient_id');      // §59
            $table->index('doctor_id');
            $table->index('scheduled_for');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dme_appointments');
    }
};
