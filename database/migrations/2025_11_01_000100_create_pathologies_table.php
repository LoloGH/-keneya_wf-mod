<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue de pathologies (v3.2.9, point 1).
 *
 * Volontairement pauvre : un nom, rien d'autre. Il sert a regrouper des
 * patients pour une diffusion, « tous ceux suivis pour du diabete », et non
 * a coder un diagnostic. Un vrai codage medical (CIM-10) serait un autre
 * sujet, avec d'autres exigences.
 *
 * Le rattachement se fait sur la visite et non sur le patient : une personne
 * consulte pour une raison en janvier et pour une autre en mars.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pathologies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('visits', function (Blueprint $table) {
            // Toujours facultatif : renseigne a la conclusion de consultation
            // quand le medecin le juge utile, jamais exige. Une cloture ne doit
            // pas dependre d'un champ de confort.
            $table->foreignId('pathology_id')->nullable()->after('service_id')->constrained();
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pathology_id');
        });

        Schema::dropIfExists('pathologies');
    }
};
