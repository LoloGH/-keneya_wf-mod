<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caisse (addendum v2, point 9).
 *
 * La caisse n'est volontairement pas un service de la table `services` : un
 * paiement n'a ni file d'attente ni renvoi. C'est une section des interfaces
 * existantes — « Caisse Ticket » a l'accueil, « Caisse Services » au service.
 *
 * Montants en FCFA, sans decimales (confirme dans l'addendum v3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('visit_id')->nullable()->constrained();
            $table->enum('type', ['ticket', 'service']); // ticket = consultation, service = acte
            $table->foreignId('service_id')->nullable()->constrained(); // pour « Caisse Services » : quel acte
            $table->decimal('amount', 10, 0);
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->foreignId('recorded_by_user_id')->constrained('users');
            $table->timestamps();

            $table->index(['patient_id', 'status']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
