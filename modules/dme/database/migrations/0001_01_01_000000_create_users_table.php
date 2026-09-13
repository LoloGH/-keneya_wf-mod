<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Annuaire des professionnels du DME.
 *
 * Le module ne gère plus l'authentification : la session vient de
 * l'application hôte. La table `users` reste néanmoins l'annuaire des
 * praticiens du dossier médical : c'est elle que référencent les
 * prescripteurs, les exécutants d'un soin, les auteurs d'un compte rendu
 * et les colonnes `created_by` de tout le dossier.
 *
 * Elle n'est créée que si l'hôte n'en a pas déjà une : montée dans une
 * application qui possède ses propres utilisateurs, cette migration ne
 * fait rien, et les migrations suivantes se contentent d'ajouter les
 * colonnes professionnelles à la table existante.
 *
 * Les tables `sessions` et `password_reset_tokens` ne sont volontairement
 * pas créées ici : elles relèvent de l'authentification, donc de l'hôte.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            return;
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // La table n'est supprimée que si le module l'a lui-même créée :
        // le repli inverse, effacer les utilisateurs de l'hôte, serait
        // catastrophique. On ne peut pas le savoir après coup, on
        // s'abstient donc toujours.
    }
};
