<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notes de releve entre equipes (v3.2.3, point 4).
 *
 * La rotation du personnel sur un patient hospitalise est deja reglee
 * structurellement : « Patients hospitalises » n'est filtre que par service, et
 * les soins sont ouverts a tout le personnel de garde. Aucun transfert
 * explicite n'est donc necessaire.
 *
 * Ce qui manquait est d'un autre ordre : ce qu'une equipe a besoin de dire a la
 * suivante et qu'aucune colonne ne capture, « il a mal dormi », « la famille
 * doit passer ce matin ». Du texte libre, date et signe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('handoff_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospitalization_id')->constrained();
            // L'auteur est un compte, pas un medecin : une note de releve est
            // ecrite aussi bien par un infirmier que par un praticien.
            $table->foreignId('written_by_user_id')->constrained('users');
            $table->text('content');
            $table->timestamps();

            $table->index(['hospitalization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handoff_notes');
    }
};
