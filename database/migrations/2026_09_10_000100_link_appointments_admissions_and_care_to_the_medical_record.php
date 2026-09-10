<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les rendez-vous, les hospitalisations et les soins rejoignent le dossier
 * medical (v3.3.2).
 *
 * Trois actes de WorkFlow n'atteignaient pas le DME : fixer un rendez-vous,
 * hospitaliser, prescrire des soins. Le module a pourtant ses trois tables et
 * ses trois onglets — « Rendez-vous », « Hospitalisations », « Soins » — qui
 * restaient vides quoi qu'on fasse. Les ordonnances, les consultations et les
 * examens, eux, avaient recu leur action de pont ; ces trois-la ont ete
 * oubliees.
 *
 * Ces colonnes disent quelle ligne du dossier correspond a quelle ligne de
 * WorkFlow. Sans elles, une sortie d'hospitalisation ne saurait pas quelle
 * hospitalisation cloturer au dossier, et l'on recreerait une ligne au lieu de
 * mettre a jour la sienne.
 *
 * Aucune cle etrangere, comme pour `referrals.dme_lab_order_id` : la table
 * visee appartient au module, qui peut ne pas etre monte. Une reference nulle
 * se lit comme « pas encore projete », ce que les actions savent traiter.
 *
 * Les soins font exception a la correspondance un pour un : une prescription
 * de WorkFlow produit autant de lignes qu'il y a d'administrations — neuf
 * lignes pour « toutes les 8 heures pendant 3 jours » — la ou le dossier
 * medical porte une seule prescription, avec sa frequence et ses bornes. Les
 * neuf occurrences pointent donc vers le meme soin programme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('dme_appointment_id')->nullable()->after('status');
            $table->index('dme_appointment_id');
        });

        Schema::table('hospitalizations', function (Blueprint $table) {
            $table->unsignedBigInteger('dme_hospitalization_id')->nullable()->after('status');
            $table->index('dme_hospitalization_id');
        });

        Schema::table('care_tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('dme_care_order_id')->nullable()->after('status');
            $table->index('dme_care_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('care_tasks', function (Blueprint $table) {
            $table->dropIndex(['dme_care_order_id']);
            $table->dropColumn('dme_care_order_id');
        });

        Schema::table('hospitalizations', function (Blueprint $table) {
            $table->dropIndex(['dme_hospitalization_id']);
            $table->dropColumn('dme_hospitalization_id');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['dme_appointment_id']);
            $table->dropColumn('dme_appointment_id');
        });
    }
};
