<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retours des patients et des visiteurs (v3.2.8, point 4).
 *
 * Une seule table pour les trois natures de retour, sondage, reclamation,
 * constat : elles partagent le meme contexte (qui, quel service, quand) et le
 * meme cycle de traitement. Les separer aurait triple le travail de
 * l'administration pour la meme lecture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_entries', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['satisfaction_survey', 'complaint', 'incident_report']);

            $table->foreignId('patient_id')->nullable()->constrained();
            $table->foreignId('visitor_id')->nullable()->constrained();

            // Constat redige par le personnel depuis sa propre interface.
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users');

            // La personne concernee par la note « personnel ».
            $table->foreignId('handled_by_user_id')->nullable()->constrained('users');

            $table->foreignId('service_id')->nullable()->constrained();

            // Nuls pour une reclamation ou un constat : ces notes n'ont de sens
            // que dans un sondage de satisfaction.
            $table->unsignedTinyInteger('rating_care')->nullable();
            $table->unsignedTinyInteger('rating_staff')->nullable();

            $table->text('content')->nullable();

            $table->enum('status', ['new', 'reviewed', 'resolved'])->default('new');
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // Les deux lectures de l'administration : par type et par statut.
            $table->index(['type', 'status']);
            $table->index('created_at');
        });

        Schema::table('visitors', function (Blueprint $table) {
            // Qui a recu ce visiteur a l'accueil : c'est la personne que sa
            // note « personnel » concerne.
            $table->foreignId('registered_by_user_id')->nullable()->after('service_id')->constrained('users');

            // L'adresse de la page de retour du visiteur. Un UUID, jamais le
            // `visitor_code` ni l'id : l'URL ne doit pas se deviner de proche
            // en proche. Pas de code a quatre chiffres en revanche : un
            // visiteur n'a pas de dossier medical a proteger, et le contenu de
            // cette page n'est pas medical.
            $table->uuid('feedback_token')->nullable()->unique()->after('token');

            // Marque l'envoi du lien de retour, pour ne jamais le renvoyer deux
            // fois. Renseignee meme quand aucun envoi n'a lieu, visiteur sans
            // numero, pour que la tache planifiee cesse d'y revenir.
            $table->timestamp('feedback_link_sent_at')->nullable()->after('reason');

            $table->index('feedback_link_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registered_by_user_id');
            $table->dropColumn(['feedback_token', 'feedback_link_sent_at']);
        });

        Schema::dropIfExists('feedback_entries');
    }
};
