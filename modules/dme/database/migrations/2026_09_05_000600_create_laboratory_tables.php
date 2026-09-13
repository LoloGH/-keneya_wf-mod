<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laboratoire : demandes, examens demandés et résultats.
 *
 * Correspondance FHIR visée : ServiceRequest / DiagnosticReport (§44).
 * Chaque résultat conserve sa valeur de référence afin de rester
 * interprétable des années plus tard, même si le référentiel évolue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dme_lab_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique(); // LAB-2026-000001
            $table->foreignId('patient_id')->constrained('dme_patients')->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('dme_consultations')->nullOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->dateTime('requested_at');
            $table->enum('priority', ['routine', 'urgent', 'vital'])->default('routine');
            $table->text('indication')->nullable();
            $table->enum('status', ['requested', 'in_progress', 'available', 'validated', 'cancelled'])
                ->default('requested');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index('patient_id');      // §59
            $table->index('status');
            $table->index('requested_at');
        });

        Schema::create('dme_lab_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_id')->constrained('dme_lab_orders')->cascadeOnDelete();
            $table->string('exam_name');
            $table->string('exam_code')->nullable();  // LOINC lorsque disponible
            $table->string('category')->nullable();   // hématologie, biochimie...
            $table->enum('status', ['requested', 'in_progress', 'available', 'validated'])
                ->default('requested');
            $table->timestamps();

            $table->index('lab_order_id');
        });

        Schema::create('dme_lab_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_item_id')->constrained('dme_lab_order_items')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('dme_patients')->cascadeOnDelete();

            $table->string('parameter');
            $table->string('value')->nullable();
            $table->string('unit')->nullable();
            $table->string('reference_range')->nullable();
            $table->enum('flag', ['normal', 'low', 'high', 'critical'])->default('normal');
            $table->text('comment')->nullable();

            $table->dateTime('measured_at')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('validated_at')->nullable();
            $table->timestamps();

            $table->index('patient_id');
            $table->index('lab_order_item_id');
            $table->index('flag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dme_lab_results');
        Schema::dropIfExists('dme_lab_order_items');
        Schema::dropIfExists('dme_lab_orders');
    }
};
