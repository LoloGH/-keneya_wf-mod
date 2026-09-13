<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Services / unités fonctionnelles de l'établissement.
 *
 * Correspondance FHIR visée : Organization (§44). Le champ `code` est
 * l'identifiant stable exploitable lors d'une future intégration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dme_services', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type')->default('clinical'); // clinical, medico_technical, administrative
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dme_services');
    }
};
