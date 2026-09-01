<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traçabilite des SMS (v3.2.8, file d'attente SMS).
 *
 * Jusqu'ici, un envoi qui echouait finissait dans `storage/logs` : personne ne
 * l'ouvrait, et une passerelle en panne pouvait le rester des jours sans que
 * rien ne le signale. Cette table remplace ce journal par une trace consultable
 * depuis l'administration, avec un statut par message.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->string('to');
            $table->text('body');

            // Relation polymorphe volontairement souple : un SMS peut porter
            // sur un patient, un visiteur, un renvoi... ou sur rien du tout.
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();

            $table->enum('status', ['queued', 'sent', 'delivered', 'failed'])->default('queued');

            // Identifiant renvoye par SMSGate a l'acceptation du message. Il ne
            // prouve pas la remise (voir docs/exploitation-demo.md), mais c'est
            // lui qu'il faudrait interroger le jour ou la passerelle exposera
            // un etat de remise.
            $table->string('provider_message_id')->nullable();

            $table->text('failure_reason')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            // Les deux lectures reelles de la table : la liste filtree par
            // statut, et le compteur d'echecs des dernieres 24 h.
            $table->index(['status', 'created_at']);
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
