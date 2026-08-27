<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications du personnel (v3.2.3, point 2).
 *
 * Une ligne par destinataire, pas une notification diffusee : c'est ce qui
 * permet de dire « lue » pour l'un et pas pour l'autre, et de ne cibler que le
 * personnel effectivement de garde.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            // Colonne `string` et non `enum` : la liste des types s'allonge a
            // chaque nouveau declencheur, et l'application la valide deja.
            $table->string('type', 40);
            $table->string('title');
            // Route interne a ouvrir au clic. Nulle si la notification n'a pas
            // d'ecran a proposer.
            $table->string('link')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // La cloche ne pose qu'une question : combien de non-lues pour ce
            // compte, et lesquelles en dernier.
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            // Un rappel par rendez-vous, jamais deux : la tache planifiee
            // repasse toutes les quinze minutes sur la meme fenetre.
            $table->timestamp('reminder_sent_at')->nullable()->after('visit_id');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });

        Schema::dropIfExists('staff_notifications');
    }
};
