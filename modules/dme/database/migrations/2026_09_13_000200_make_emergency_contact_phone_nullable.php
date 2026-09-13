<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une personne à prévenir peut ne pas avoir de téléphone.
 *
 * La colonne était obligatoire, ce qui supposait qu'on ne connaît un proche
 * qu'avec son numéro. L'accueil de l'hôte enregistre un accompagnateur avec un
 * nom et un lien de parenté, le numéro étant facultatif : sans cette
 * souplesse, l'accompagnateur d'un patient venu à pied depuis le quartier ne
 * pouvait tout simplement pas rejoindre le dossier.
 *
 * Un nom et un lien valent mieux que rien : c'est déjà de quoi savoir qui
 * chercher dans la salle d'attente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dme_emergency_contacts', function (Blueprint $table) {
            $table->string('phone')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('dme_emergency_contacts', function (Blueprint $table) {
            $table->string('phone')->nullable(false)->change();
        });
    }
};
