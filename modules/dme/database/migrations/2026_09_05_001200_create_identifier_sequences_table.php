<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Séquences des identifiants métier lisibles (§37).
 *
 * Une ligne par (préfixe, année). L'incrément est réalisé sous
 * transaction avec verrou pessimiste afin que deux créations simultanées
 * ne produisent jamais le même numéro. Les identifiants restent stables :
 * ils ne sont jamais réattribués ni recalculés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dme_identifier_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('prefix');
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('current_value')->default(0);
            $table->timestamps();

            $table->unique(['prefix', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dme_identifier_sequences');
    }
};
