<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le nom du patient se saisit en deux champs, comme au dossier medical.
 *
 * L'accueil tenait un seul champ « nom complet » ; le DME en tient deux, un
 * nom et un prenom. La projection comblait l'ecart en versant le nom entier
 * dans `last_name` et en laissant le prenom vide : le dossier medical, les
 * ordonnances et les documents imprimes affichaient donc une identite que
 * personne n'avait saisie sous cette forme. Les deux formulaires demandent
 * desormais la meme chose.
 *
 * La colonne `name` reste, et reste la reference pour tout ce qui affiche une
 * identite d'un bloc : ticket, SMS, recherche, journal d'audit. Elle n'est
 * plus saisie, elle est composee — « prenom nom » — a chaque enregistrement
 * du modele. La supprimer aurait oblige a reecrire une centaine d'affichages
 * pour un gain nul.
 *
 * Reprise des dossiers existants : le premier mot est le prenom, le reste le
 * nom de famille. C'est l'ordre dans lequel « Aminata Traore » a ete saisi, et
 * le seul qui laisse la chaine affichee identique apres la migration. Le
 * decoupage est une supposition, jamais une certitude ; il se corrige a
 * l'accueil, dossier par dossier, par la correction d'identite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('first_name', 120)->default('')->after('name');
            $table->string('last_name', 120)->default('')->after('first_name');
            $table->index(['last_name', 'first_name']);
        });

        foreach (DB::table('patients')->select('id', 'name')->orderBy('id')->cursor() as $patient) {
            [$prenom, $nom] = self::decouper((string) $patient->name);

            DB::table('patients')->where('id', $patient->id)->update([
                'first_name' => $prenom,
                'last_name' => $nom,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex(['last_name', 'first_name']);
            $table->dropColumn(['first_name', 'last_name']);
        });
    }

    /**
     * Le premier mot au prenom, le reste au nom. Un nom d'un seul mot part
     * entier au nom de famille : c'est ce qui reste vrai le plus souvent, et
     * un prenom seul sans nom ne se rattache a rien.
     *
     * @return array{0: string, 1: string}
     */
    private static function decouper(string $complet): array
    {
        $morceaux = preg_split('/\s+/', trim($complet), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($morceaux) <= 1) {
            return ['', implode(' ', $morceaux)];
        }

        return [(string) array_shift($morceaux), implode(' ', $morceaux)];
    }
};
