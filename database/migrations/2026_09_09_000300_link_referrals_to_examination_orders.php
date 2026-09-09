<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La demande d'examen voyage avec le patient (v3.3.1).
 *
 * Jusqu'ici, demander un examen et envoyer le patient le faire etaient deux
 * gestes sans lien : le medecin remplissait « Examen biologique » dans le
 * dossier medical, puis renvoyait le patient vers le plateau technique par un
 * autre ecran. Le technicien recevait donc un patient sans savoir ce qu'on lui
 * demandait, et la demande dormait dans le dossier sans destinataire.
 *
 * Deux ajouts pour recoudre cela :
 *
 *  - `services.exam_kind` dit ce que realise un service : des analyses, de
 *    l'imagerie, ou rien. C'est ce qui permet au formulaire de demande de
 *    s'afficher tout seul quand le medecin choisit l'echographie. Un nom de
 *    service ne se devine pas : l'administrateur le declare.
 *  - `referrals.dme_lab_order_id` / `dme_imaging_order_id` relient le renvoi a
 *    la demande qu'il transporte.
 *
 * Aucune cle etrangere sur ces deux dernieres colonnes : la table visee
 * appartient au module, qui peut ne pas etre monte. Une reference nulle ou
 * orpheline se lit comme « pas de demande attachee », ce que l'ecran du
 * technicien sait afficher.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('exam_kind', 20)->nullable()->after('service_kind_id');
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->unsignedBigInteger('dme_lab_order_id')->nullable()->after('billable_item_id');
            $table->unsignedBigInteger('dme_imaging_order_id')->nullable()->after('dme_lab_order_id');
            $table->index('dme_lab_order_id');
            $table->index('dme_imaging_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropIndex(['dme_lab_order_id']);
            $table->dropIndex(['dme_imaging_order_id']);
            $table->dropColumn(['dme_lab_order_id', 'dme_imaging_order_id']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('exam_kind');
        });
    }
};
