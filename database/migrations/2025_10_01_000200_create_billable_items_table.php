<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue de tarifs (v3.2.8, point 3).
 *
 * Le montant se saisissait librement a la caisse : rien ne disait ce que le
 * patient payait, ni ne garantissait que deux caissiers demandent la meme somme
 * pour le meme acte. Ce catalogue s'administre comme les autres — types de
 * service, types de personnel — et devient la source du montant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billable_items', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // « Ticket de consultation », « Echographie abdominale »…

            // Vide pour un tarif generique, comme le ticket de consultation, qui
            // ne depend d'aucun plateau technique.
            $table->foreignId('service_id')->nullable()->constrained();

            $table->decimal('price', 10, 0); // FCFA, sans decimales
            $table->timestamps();

            $table->index('service_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            // Nullable : les encaissements anterieurs au catalogue n'ont pas
            // d'acte rattache, et il ne serait pas honnete de leur en inventer un.
            $table->foreignId('billable_item_id')->nullable()->after('service_id')->constrained();

            // Une dérogation de montant est explicite et tracee : le caissier
            // peut corriger, mais cela ne doit pas etre le comportement ordinaire.
            $table->decimal('catalog_price', 10, 0)->nullable()->after('amount');
        });

        Schema::table('referrals', function (Blueprint $table) {
            // L'acte precis choisi par le medecin voyage avec la visite jusqu'a
            // la caisse : le caissier n'a plus a demander ni a deviner.
            $table->foreignId('billable_item_id')->nullable()->after('to_service_id')->constrained();
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('billable_item_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('billable_item_id');
            $table->dropColumn('catalog_price');
        });

        Schema::dropIfExists('billable_items');
    }
};
