<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Troisieme type de service : la caisse (v3.2, point 6).
 *
 * Les deux caisses sont des services a part entiere — meme file, meme token,
 * meme « Appeler le suivant ». Colonne `string` plutot qu'enum modifie : les
 * valeurs sont validees par l'application (constantes de App\Models\Service),
 * et etendre un enum en place se comporte differemment selon MariaDB et SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('kind', 30)->default('clinique')->change();
        });
    }

    public function down(): void
    {
        // Revenir en arriere, c'est revenir a un hopital sans caisse : les deux
        // services de caisse et les files qui y patientent n'ont plus de sens.
        // Sans ce nettoyage, MariaDB refuse de retrecir l'enum (« Data
        // truncated for column 'kind' ») des qu'une ligne vaut « caisse ».
        $caisses = DB::table('services')->where('kind', 'caisse')->pluck('id');

        if ($caisses->isNotEmpty()) {
            DB::table('patient_history')->whereIn('service_id', $caisses)->delete();
            DB::table('payments')->whereIn('service_id', $caisses)->update(['service_id' => null]);
            DB::table('visitors')->whereIn('service_id', $caisses)->delete();
            DB::table('visits')->whereIn('service_id', $caisses)->delete();
            DB::table('services')->whereIn('id', $caisses)->delete();
        }

        Schema::table('services', function (Blueprint $table) {
            $table->enum('kind', ['clinique', 'plateau_technique'])->default('clinique')->change();
        });
    }
};
