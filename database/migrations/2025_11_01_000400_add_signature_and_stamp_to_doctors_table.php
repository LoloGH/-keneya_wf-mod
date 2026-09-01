<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Signature et tampon du medecin (v3.2.9, point 2).
 *
 * Deux images distinctes et non une seule : la signature engage la personne,
 * le tampon atteste de sa qualite. Sur une ordonnance malienne les deux
 * figurent, et un praticien peut avoir l'une sans l'autre.
 *
 * Le tampon de l'etablissement, lui, ne vit pas ici mais dans `settings` : il
 * est institutionnel et commun a tous les medecins, pas personnel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->string('signature_path')->nullable()->after('phone');
            $table->string('stamp_path')->nullable()->after('signature_path');
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn(['signature_path', 'stamp_path']);
        });
    }
};
