<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separation de l'identite et du passage (addendum v3).
 *
 * `patients` redevient une table d'identite pure : un patient_code a vie. Un
 * episode de soins vit desormais dans `visits`, ce qui permet a un meme
 * patient de revenir plusieurs fois, sous le meme identifiant, sans jamais
 * ecraser son passage precedent.
 *
 * Les donnees de demo sont jetables, mais la migration reprend quand meme les
 * lignes existantes : chaque patient deja enregistre reçoit une visite portant
 * son service, son token et son statut d'origine. La migration est donc sans
 * perte dans les deux sens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('service_id')->constrained();
            $table->unsignedInteger('token');
            $table->enum('status', ['waiting', 'called', 'closed'])->default('waiting');
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['service_id', 'status']);
            $table->index(['patient_id', 'opened_at']);
        });

        // Reprise des passages en cours : une visite par patient existant.
        foreach (DB::table('patients')->orderBy('id')->get() as $patient) {
            DB::table('visits')->insert([
                'patient_id' => $patient->id,
                'service_id' => $patient->service_id,
                'token' => $patient->token,
                'status' => $patient->status,
                'opened_at' => $patient->created_at,
                'closed_at' => null,
                'created_at' => $patient->created_at,
                'updated_at' => $patient->updated_at,
            ]);
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->dropIndex(['service_id', 'status']);
            $table->dropColumn(['service_id', 'token', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('service_id')->nullable()->constrained();
            $table->unsignedInteger('token')->default(0);
            $table->enum('status', ['waiting', 'called'])->default('waiting');

            $table->index(['service_id', 'status']);
        });

        // On rend a chaque patient sa visite la plus recente, en repliant le
        // statut `closed` (inexistant avant) sur `called`.
        foreach (DB::table('patients')->orderBy('id')->get() as $patient) {
            $visit = DB::table('visits')
                ->where('patient_id', $patient->id)
                ->orderByDesc('opened_at')
                ->orderByDesc('id')
                ->first();

            if (! $visit) {
                continue;
            }

            DB::table('patients')->where('id', $patient->id)->update([
                'service_id' => $visit->service_id,
                'token' => $visit->token,
                'status' => $visit->status === 'closed' ? 'called' : $visit->status,
            ]);
        }

        Schema::dropIfExists('visits');
    }
};
