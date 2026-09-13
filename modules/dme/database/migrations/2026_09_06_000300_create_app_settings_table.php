<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réglages modifiables depuis l'écran Paramètres : coordonnées de
 * l'établissement, préfixes des identifiants métier. Une simple table
 * clé/valeur suffit : ces réglages sont peu nombreux et lus en bloc au
 * démarrage de chaque requête (AppServiceProvider) pour surcharger
 * config('dme.*'), qui reste la valeur par défaut tant qu'aucune
 * ligne ne l'y remplace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dme_app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dme_app_settings');
    }
};
