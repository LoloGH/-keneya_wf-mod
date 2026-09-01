<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Numero de telephone du personnel (v3.2.9, point 1).
 *
 * Jusqu'ici seuls les medecins etaient joignables : `doctors.phone` existait,
 * mais ni une receptionniste, ni un caissier, ni un personnel generique
 * n'avait de numero. L'envoi groupe vise « un membre du personnel », pas
 * seulement un medecin — il fallait donc porter le numero la ou se trouve la
 * personne, sur son compte, et non sur l'une de ses tables de rattachement.
 *
 * `doctors.phone` reste en place et fait office de repli : les numeros deja
 * saisis continuent de servir sans avoir a etre ressaisis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile', 30)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('mobile');
        });
    }
};
