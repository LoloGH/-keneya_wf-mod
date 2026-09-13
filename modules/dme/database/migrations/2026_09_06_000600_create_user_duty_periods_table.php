<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Garde planifiée à l'avance par l'administrateur, en plus du bouton de
 * prise de garde manuelle (§ migration add_on_duty_to_users_table).
 *
 * activated_at / deactivated_at tracent le moment où la tâche planifiée
 * SyncDutyPeriods a effectivement basculé is_on_duty pour cette période :
 * ils rendent la bascule idempotente et empêchent de revenir sur une
 * prise de garde que l'intéressé aurait entre-temps quittée à la main.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dme_user_duty_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('deactivated_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dme_user_duty_periods');
    }
};
