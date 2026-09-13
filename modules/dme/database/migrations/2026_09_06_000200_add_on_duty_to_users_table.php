<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prise de garde.
 *
 * Un soin programmé sans destinataire nommé doit être visible « par le
 * personnel de garde du service prescripteur ». Il faut donc savoir qui
 * est de garde. Un drapeau porté par l'utilisateur suffit à cette étape :
 * il est déclaratif, horodaté, et journalisé à chaque bascule. Un
 * véritable tableau de service, plages, roulements, remplacements,
 * relève d'un module de planification qui n'existe pas encore.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_on_duty')) {
                $table->boolean('is_on_duty')->default(false);
                $table->index(['service_id', 'is_on_duty']);
            }

            if (! Schema::hasColumn('users', 'on_duty_since')) {
                $table->dateTime('on_duty_since')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['service_id', 'is_on_duty']);
            $table->dropColumn(['is_on_duty', 'on_duty_since']);
        });
    }
};
