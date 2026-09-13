<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centre de notifications (§33).
 *
 * Table standard des notifications Laravel, enrichie des colonnes
 * nécessaires au filtrage par catégorie et par patient dans l'interface.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dme_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');

            $table->string('category')->nullable(); // appointment, lab_result, prescription...
            $table->string('level')->default('info'); // info, warning, critical
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->string('action_url')->nullable();

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('patient_id');
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dme_notifications');
    }
};
