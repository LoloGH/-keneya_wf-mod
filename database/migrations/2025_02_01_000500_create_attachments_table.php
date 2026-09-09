<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pieces jointes (addendum v2, point 3) : image d'echographie, PDF de
 * laboratoire, rattachees a un resultat de renvoi ou a une entree
 * d'historique. Stockage sur le disque local du conteneur `app`, sur le
 * volume Docker `keneya_storage` : jamais sur un service cloud, la
 * connectivite du site ne le permet pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('visit_id')->nullable()->constrained();
            // La table s'appelle `patient_history` au singulier : sans le nom
            // explicite, Laravel deduirait `patient_histories`.
            $table->foreignId('patient_history_id')->nullable()->constrained('patient_history');
            $table->foreignId('referral_id')->nullable()->constrained();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by_user_id')->constrained('users');
            $table->timestamps();

            $table->index(['patient_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
