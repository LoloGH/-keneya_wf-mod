<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Libellé d'affichage des rôles créés depuis l'écran Paramètres.
 *
 * Les sept rôles d'origine gardent leur libellé français dans
 * Rbac::roleLabels(), ce champ ne sert qu'aux rôles ajoutés par
 * l'administrateur, dont le nom technique (utilisé par hasRole()) est
 * dérivé du libellé mais n'est pas forcément lisible tel quel.
 */
return new class extends Migration
{
    public function up(): void
    {
        // La table des rôles appartient à spatie/laravel-permission :
        // l'hôte l'a peut-être déjà, avec ou sans cette colonne.
        if (Schema::hasColumn('roles', 'label')) {
            return;
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->string('label')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('label');
        });
    }
};
