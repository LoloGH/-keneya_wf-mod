<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une note par poste rencontre (v3.2.9, point 3).
 *
 * Correction de conception du module de retours livre en v3.2.8, avant sa
 * premiere utilisation reelle : une note unique « le personnel » melait dans
 * un seul chiffre l'agent d'accueil, le caissier et le medecin. Un patient
 * tres bien recu mais mal oriente n'avait aucun moyen de le dire, et
 * l'administration aucun moyen de savoir ou agir.
 *
 * `feedback_entries.rating_care` reste la note globale du parcours ; c'est la
 * note « personnel » qui eclate en autant de lignes que d'etapes traversees.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_survey_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_entry_id')->constrained()->cascadeOnDelete();

            // Nul quand l'etape n'identifie personne : l'accueil et la caisse
            // ne consignent pas quel agent a servi. La note porte alors sur le
            // poste, ce qui reste exploitable : mieux vaut une note sans
            // destinataire qu'une note attribuee au hasard.
            $table->foreignId('user_id')->nullable()->constrained();

            $table->string('post_label'); // « Accueil », « Medecine Generale : Dr X »...
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index('feedback_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_survey_ratings');
    }
};
