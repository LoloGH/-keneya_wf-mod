<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soins programmés : la prescription de soin, distincte du soin réalisé.
 *
 * `nursing_notes` consigne ce qui a été fait ; cette table porte ce qui
 * est demandé : par qui, pour quand, à quelle fréquence, et à qui c'est
 * confié. Un soin sans destinataire nommé revient au personnel de garde
 * du service prescripteur, d'où la conservation de `service_id` sur la
 * ligne : le service du prescripteur peut changer, la portée du soin, non.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dme_care_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('patient_id')->constrained('dme_patients')->cascadeOnDelete();
            $table->foreignId('hospitalization_id')->nullable()->constrained('dme_hospitalizations')->nullOnDelete();

            $table->foreignId('prescriber_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('dme_services')->nullOnDelete();
            // Null = soin ouvert au personnel de garde du service prescripteur.
            $table->foreignId('assigned_nurse_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->text('instructions')->nullable();
            $table->string('frequency')->nullable();
            $table->string('priority')->default('routine');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();

            $table->string('status')->default('planned');
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('outcome')->nullable();

            $table->timestamps();

            $table->index(['patient_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
            $table->index(['assigned_nurse_id', 'status']);
            $table->index(['service_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dme_care_orders');
    }
};
