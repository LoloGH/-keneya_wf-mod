<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le caissier, quatrieme role cloisonne (v3.2, point 6).
 *
 * Dans cet hopital les medecins n'encaissent jamais : un patient regle a la
 * caisse avant d'etre oriente vers le service qui le prendra en charge. La
 * caisse coordonne financierement tous les services, elle a donc son role et
 * son interface propres, sur le modele de `receptionists` et `doctors`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashiers');
    }
};
