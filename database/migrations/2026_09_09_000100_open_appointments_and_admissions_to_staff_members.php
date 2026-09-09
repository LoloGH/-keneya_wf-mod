<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rendez-vous et admissions ouverts au personnel generique (v3.3.1).
 *
 * Les capacites « Donner un rendez-vous » et « Hospitaliser un patient » se
 * cochent depuis toujours sur un type de personnel a interface dediee, mais
 * elles ne produisaient rien : les deux tables exigeaient une ligne `doctors`
 * que ce personnel n'a pas. Un echographiste a qui l'admin avait tout coche se
 * retrouvait avec une interface qui ne savait rien faire de ce qu'on lui avait
 * accorde.
 *
 * Le motif est celui deja pose pour les renvois et l'historique
 * (2025_04_01_000300) : une colonne par rattachement, l'une **ou** l'autre,
 * jamais les deux, et jamais aucune. On n'elargit pas `doctors` a tout le
 * personnel — ce serait fabriquer de faux medecins, avec les droits de
 * l'interface /service.
 *
 * `care_tasks.prescribed_by_doctor_id` reste inchange : prescrire un soin est
 * une decision medicale, et la capacite correspondante n'existe pas hors du
 * role medecin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('staff_member_id')->nullable()->after('doctor_id')->constrained('staff_members');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('doctor_id')->nullable()->change();
        });

        Schema::table('hospitalizations', function (Blueprint $table) {
            $table->foreignId('admitted_by_staff_member_id')->nullable()
                ->after('admitted_by_doctor_id')->constrained('staff_members');
        });

        Schema::table('hospitalizations', function (Blueprint $table) {
            $table->unsignedBigInteger('admitted_by_doctor_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Un rendez-vous ou une admission sans medecin n'a pas d'equivalent
        // dans l'ancien schema : la ligne est retiree plutot que rattachee a
        // un medecin invente.
        DB::table('appointments')->whereNull('doctor_id')->delete();
        DB::table('hospitalizations')->whereNull('admitted_by_doctor_id')->delete();

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_member_id');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('doctor_id')->nullable(false)->change();
        });

        Schema::table('hospitalizations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admitted_by_staff_member_id');
        });

        Schema::table('hospitalizations', function (Blueprint $table) {
            $table->unsignedBigInteger('admitted_by_doctor_id')->nullable(false)->change();
        });
    }
};
