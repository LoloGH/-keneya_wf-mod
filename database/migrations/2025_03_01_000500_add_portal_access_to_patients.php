<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Acces du patient a ses documents (v3.2, point 7).
 *
 * `portal_token` est un UUID : jamais le patient_code ni l'id auto-incremente
 * dans l'URL, sinon le lien serait devinable de proche en proche.
 * `access_code` est le code a quatre chiffres remis a l'accueil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('access_code', 4)->nullable()->after('crno');
            $table->uuid('portal_token')->nullable()->unique()->after('access_code');
        });

        // Les dossiers deja ouverts recoivent leurs identifiants d'acces.
        foreach (DB::table('patients')->whereNull('portal_token')->orderBy('id')->get() as $patient) {
            DB::table('patients')->where('id', $patient->id)->update([
                'access_code' => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
                'portal_token' => (string) Str::uuid(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropUnique(['portal_token']);
            $table->dropColumn(['access_code', 'portal_token']);
        });
    }
};
