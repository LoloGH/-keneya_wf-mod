<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diffusions de SMS depuis l'administration (v3.2.9, point 1).
 *
 * Une ligne par envoi groupe, et non une par destinataire : ce sont les
 * `sms_messages` qui portent le detail, rattaches a cette diffusion par leur
 * relation polymorphe. On lit donc « ce message est parti a 412 personnes »
 * ici, et « voici ce qu'il est advenu de chacun » la-bas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sent_by_user_id')->constrained('users');
            $table->text('content');

            // 'staff', 'single_patient', 'patient_group', 'all_patients'
            $table->string('target_type');

            // Les filtres retenus, tels qu'ils ont ete choisis : ex.
            // {"service_id": 3, "pathology_id": 5}. Conserves en clair pour
            // qu'une diffusion reste explicable des mois plus tard, meme si le
            // service ou la pathologie ont ete renommes depuis.
            $table->json('target_filters')->nullable();

            $table->unsignedInteger('recipient_count');
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_messages');
    }
};
