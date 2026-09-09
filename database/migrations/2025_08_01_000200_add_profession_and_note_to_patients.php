<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profession et note libre au dossier patient (v3.2.5).
 *
 * Deux renseignements que l'accueil prend a l'oral et qui n'avaient nulle part
 * ou aller. La profession fait partie de l'identite au meme titre que l'age ;
 * la note est un mot de l'accueil au service, « malentendant », « accompagne
 * par sa fille », et reste facultative.
 *
 * La note vit sur le patient et non sur le passage : ce qu'elle porte est vrai
 * a chaque venue, pas seulement celle du jour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('profession', 120)->nullable()->after('gender');
            $table->text('note')->nullable()->after('crno');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['profession', 'note']);
        });
    }
};
