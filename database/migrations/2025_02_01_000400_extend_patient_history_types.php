<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nouveaux types d'evenements du parcours : cloture de renvoi, cloture de
 * dossier, ordonnance.
 *
 * Meme raisonnement que pour `referrals.status` : une colonne `string`
 * validee par l'application, plutot qu'un enum a etendre a chaque ajout de
 * type — la table est append-only et deja tres sollicitee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_history', function (Blueprint $table) {
            $table->string('type', 30)->change();
        });
    }

    public function down(): void
    {
        Schema::table('patient_history', function (Blueprint $table) {
            $table->enum('type', ['registration', 'consultation', 'referral_sent', 'referral_result'])->change();
        });
    }
};
