<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rattache un retour au passage qu'il concerne.
 *
 * Un sondage note UN passage, pas un patient en general. Sans ce lien, rien ne
 * permettait de savoir si l'avis deja donne portait sur la visite d'aujourd'hui
 * ou sur celle du mois dernier, et le portail reproposait donc indefiniment
 * « Noter mon passage » a quelqu'un qui venait de repondre.
 *
 * Nullable, et le restera : un constat redige par un agent peut ne viser aucun
 * passage, et une reclamation deposee hors de tout passage reste recevable.
 * `nullOnDelete` plutot qu'une cascade : la suppression d'un dossier patient
 * efface deja ses retours par ailleurs ; ici, perdre le rattachement ne doit
 * jamais faire disparaitre un signalement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback_entries', function (Blueprint $table) {
            $table->foreignId('visit_id')->nullable()->after('patient_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('feedback_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('visit_id');
        });
    }
};
