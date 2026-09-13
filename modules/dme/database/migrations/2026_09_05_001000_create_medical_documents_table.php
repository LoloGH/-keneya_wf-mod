<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documents médicaux (§28). Correspondance FHIR : DocumentReference (§44).
 *
 * Sécurité (§42) : `storage_path` désigne un fichier sur un disque privé.
 * Ce chemin n'est jamais exposé ; le téléchargement passe exclusivement par
 * la route contrôlée /documents/{document}/download, qui vérifie la policy.
 *
 * Versionnement : un document remplacé conserve son prédécesseur via
 * `replaces_document_id`, aucun contenu médical n'est écrasé (§40).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dme_medical_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_number')->unique(); // DOC-2026-000001
            $table->foreignId('patient_id')->constrained('dme_patients')->cascadeOnDelete();

            $table->string('title');
            $table->enum('type', [
                'prescription', 'lab_result', 'imaging_report', 'discharge_summary',
                'certificate', 'medical_letter', 'consultation_report', 'imported', 'other',
            ])->default('other');
            $table->text('description')->nullable();

            // Fichier, disque privé uniquement
            $table->string('disk')->default('local');
            $table->string('storage_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum')->nullable();

            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('replaces_document_id')->nullable()
                ->constrained('dme_medical_documents')->nullOnDelete();

            $table->enum('status', ['draft', 'final', 'signed', 'archived'])->default('final');
            $table->boolean('is_generated')->default(false); // produit par l'application (PDF)

            // Rattachement facultatif à l'acte d'origine
            $table->nullableMorphs('source');

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('patient_id');    // §59
            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dme_medical_documents');
    }
};
