<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ancre la logique metier sur la visite (addendum v3).
 *
 * `referrals` et `patient_history` gardent `patient_id` — utile pour lire un
 * dossier complet d'un seul coup — mais la file d'attente, le renvoi et la
 * cloture s'appuient desormais sur `visit_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->foreignId('visit_id')->nullable()->after('patient_id')->constrained();
        });

        Schema::table('patient_history', function (Blueprint $table) {
            $table->foreignId('visit_id')->nullable()->after('patient_id')->constrained();
        });

        // Rattachement des lignes existantes a la visite reprise juste avant.
        foreach (DB::table('visits')->orderBy('id')->get() as $visit) {
            DB::table('referrals')->where('patient_id', $visit->patient_id)
                ->whereNull('visit_id')->update(['visit_id' => $visit->id]);

            DB::table('patient_history')->where('patient_id', $visit->patient_id)
                ->whereNull('visit_id')->update(['visit_id' => $visit->id]);
        }
    }

    public function down(): void
    {
        Schema::table('patient_history', function (Blueprint $table) {
            $table->dropForeign(['visit_id']);
            $table->dropColumn('visit_id');
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->dropForeign(['visit_id']);
            $table->dropColumn('visit_id');
        });
    }
};
