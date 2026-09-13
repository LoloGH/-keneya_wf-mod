<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Champs professionnels des utilisateurs.
 *
 * Correspondance FHIR visée : Practitioner (§44). Ces colonnes sont
 * strictement additives, et ajoutées une par une seulement si elles
 * manquent : montée dans une application hôte qui a déjà sa table
 * `users`, cette migration l'enrichit sans jamais heurter l'existant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'matricule')) {
                $table->string('matricule')->nullable()->unique();
            }

            foreach (['first_name', 'last_name', 'title', 'speciality', 'phone'] as $column) {
                if (! Schema::hasColumn('users', $column)) {
                    $table->string($column)->nullable();
                }
            }

            if (! Schema::hasColumn('users', 'service_id')) {
                $table->foreignId('service_id')->nullable()->constrained('dme_services')->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true);
                $table->index('is_active');
            }

            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'last_login_ip')) {
                $table->string('last_login_ip', 45)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'service_id')) {
                $table->dropConstrainedForeignId('service_id');
            }

            foreach ([
                'matricule', 'first_name', 'last_name', 'title', 'speciality',
                'phone', 'is_active', 'last_login_at', 'last_login_ip',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
