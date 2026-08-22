<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ticket visiteur (addendum v2, point 4).
 *
 * Le visiteur tire son numero dans la meme sequence que les patients du
 * service : deux personnes ne doivent jamais voir le meme numero affiche sur
 * l'ecran de salle d'attente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->unsignedInteger('token')->nullable()->after('service_id');
        });
    }

    public function down(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->dropColumn('token');
        });
    }
};
