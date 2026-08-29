<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ordonnance ligne a ligne (v3.2.6).
 *
 * L'ordonnance etait une zone de texte unique : le medecin y ecrivait tout,
 * et l'imprime rendait ce bloc tel quel. Une ordonnance se lit pourtant
 * ligne par ligne, chacune avec son medicament, sa posologie et sa duree.
 *
 * Les lignes vivent dans une colonne JSON plutot que dans une table dediee :
 * elles ne sont jamais interrogees seules, toujours lues avec l'ordonnance.
 * C'est le meme choix que pour `staff_types.capabilities`.
 *
 * `content` devient nullable et ne sert plus qu'aux ordonnances anterieures :
 * une ordonnance porte ses lignes, ou son ancien texte, jamais les deux.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->json('lines')->nullable()->after('doctor_id');
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->text('content')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Les ordonnances ecrites en lignes retrouvent un texte : sans cela,
        // elles reviendraient vides sur une colonne redevenue obligatoire.
        foreach (DB::table('prescriptions')->whereNotNull('lines')->get() as $ordonnance) {
            $lignes = json_decode((string) $ordonnance->lines, true) ?: [];

            $texte = collect($lignes)
                ->map(fn (array $ligne, int $rang) => trim(sprintf(
                    '%d. %s %s %s',
                    $rang + 1,
                    $ligne['medicament'] ?? '',
                    $ligne['posologie'] ?? '',
                    $ligne['duree'] ?? '',
                )))
                ->implode("\n");

            DB::table('prescriptions')
                ->where('id', $ordonnance->id)
                ->update(['content' => $texte ?: '(ordonnance vide)']);
        }

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->text('content')->nullable(false)->change();
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn('lines');
        });
    }
};
