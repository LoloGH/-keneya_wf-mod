<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cloture d'un renvoi (addendum v2, point 1).
 *
 * `done` = le service executant a rendu son resultat.
 * `closed` = le prescripteur en a pris connaissance et ferme la boucle.
 *
 * Le statut est porte par une colonne `string` plutot qu'un `enum` modifie :
 * l'extension d'un enum en place se comporte differemment selon MariaDB et
 * SQLite, alors que les valeurs autorisees sont de toute façon validees par
 * l'application (constantes de App\Models\Referral).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->change();
            $table->foreignId('closed_by_doctor_id')->nullable()->after('completed_by_doctor_id')->constrained('doctors');
            $table->timestamp('closed_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropForeign(['closed_by_doctor_id']);
            $table->dropColumn(['closed_by_doctor_id', 'closed_at']);
            $table->enum('status', ['pending', 'done'])->default('pending')->change();
        });
    }
};
