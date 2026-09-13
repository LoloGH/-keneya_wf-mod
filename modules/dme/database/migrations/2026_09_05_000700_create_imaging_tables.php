<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Imagerie médicale : demandes et comptes rendus.
 *
 * Correspondance FHIR visée : ImagingStudy (§44).
 *
 * Préparation DICOM/PACS (§46) : cette version stocke uniquement les
 * métadonnées (modalité, accession number, study/series instance UID) et
 * les documents associés. Aucun PACS n'est implémenté ; les colonnes
 * `accession_number` et `study_instance_uid` sont les points d'accroche
 * d'une future passerelle DICOM.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dme_imaging_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique(); // IMG-2026-000001
            $table->foreignId('patient_id')->constrained('dme_patients')->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('dme_consultations')->nullOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('modality', [
                'radiography', 'ultrasound', 'ct', 'mri', 'mammography', 'other',
            ])->default('other');
            $table->string('body_site')->nullable();
            $table->dateTime('requested_at');
            $table->dateTime('scheduled_for')->nullable();
            $table->enum('priority', ['routine', 'urgent', 'vital'])->default('routine');
            $table->text('indication')->nullable();
            $table->enum('status', ['requested', 'scheduled', 'performed', 'reported', 'cancelled'])
                ->default('requested');

            // Points d'accroche DICOM / PACS : non exploités en phase 1.
            $table->string('accession_number')->nullable()->unique();
            $table->string('study_instance_uid')->nullable();

            $table->timestamps();

            $table->index('patient_id');
            $table->index('status');
            $table->index('requested_at');
        });

        Schema::create('dme_imaging_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imaging_order_id')->constrained('dme_imaging_orders')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('dme_patients')->cascadeOnDelete();
            $table->foreignId('radiologist_id')->nullable()->constrained('users')->nullOnDelete();

            $table->longText('technique')->nullable();
            $table->longText('findings')->nullable();     // résultats
            $table->longText('conclusion')->nullable();   // compte rendu
            $table->boolean('is_abnormal')->default(false);
            $table->dateTime('reported_at')->nullable();
            $table->enum('status', ['draft', 'final', 'amended'])->default('draft');
            $table->timestamps();

            $table->index('patient_id');
            $table->index('imaging_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dme_imaging_reports');
        Schema::dropIfExists('dme_imaging_orders');
    }
};
