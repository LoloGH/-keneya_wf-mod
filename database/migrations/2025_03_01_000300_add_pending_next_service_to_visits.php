<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Routage sous condition de paiement (v3.2, point 6).
 *
 * La visite passe d'abord par une caisse ; `pending_next_service_id` retient
 * la destination reelle, ou elle basculera une fois le paiement confirme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->foreignId('pending_next_service_id')->nullable()->after('service_id')->constrained('services');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropForeign(['pending_next_service_id']);
            $table->dropColumn('pending_next_service_id');
        });
    }
};
