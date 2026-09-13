<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Horaire hebdomadaire type d'un compte : le créneau habituel de
 * présence pour chaque jour de la semaine, défini par l'administrateur
 * à la création du compte ou depuis sa fiche.
 *
 * Un jour sans créneau (starts_at et ends_at nuls) est un jour de repos.
 * Cet horaire est déclaratif, il ne bascule jamais seul la garde
 * (is_on_duty) : il sert de référence pour savoir quand un soignant
 * travaille normalement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dme_user_weekly_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // 1 = lundi ... 7 = dimanche (Carbon::dayOfWeekIso).
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dme_user_weekly_schedules');
    }
};
