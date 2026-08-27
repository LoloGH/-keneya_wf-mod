<?php

use App\Models\CareTask;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Annulation d'un soin programme (v3.2.3, point 4).
 *
 * Un soin saisi par erreur, ou une prescription arretee en cours de route, ne
 * doit pas etre efface : le dossier d'un patient ne se reecrit pas. Il prend un
 * statut « annule », qui le sort des comptes sans le sortir de l'historique.
 *
 * Le statut passe de l'enum a une colonne `string` : c'est deja le choix fait
 * pour `referrals.status`, `patient_history.type` et `services.kind`. Etendre
 * un enum en place ne se comporte pas de la meme facon sur MariaDB et SQLite,
 * et la liste des statuts est de toute facon validee par l'application.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_tasks', function (Blueprint $table) {
            $table->string('status', 20)->default(CareTask::STATUS_PENDING)->change();
        });

        Schema::table('care_tasks', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');
            $table->foreignId('cancelled_by_user_id')->nullable()->after('cancelled_at')->constrained('users');
            // Le motif est obligatoire cote application : une annulation sans
            // raison lisible ne vaut pas mieux qu'une suppression silencieuse.
            $table->string('cancellation_reason', 255)->nullable()->after('cancelled_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('care_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by_user_id');
            $table->dropColumn(['cancelled_at', 'cancellation_reason']);
        });

        // Sans ce rabattement, MariaDB refuse de retrecir l'enum (« Data
        // truncated for column 'status' »). Les soins annules deviennent des
        // soins manques — la valeur la plus proche que l'enum sait porter :
        // une administration qui n'a pas eu lieu. On ne supprime aucune ligne.
        DB::table('care_tasks')
            ->where('status', CareTask::STATUS_CANCELLED)
            ->update(['status' => CareTask::STATUS_MISSED]);

        Schema::table('care_tasks', function (Blueprint $table) {
            $table->enum('status', ['pending', 'done', 'missed'])->default('pending')->change();
        });
    }
};
