<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accompagnateurs (addendum v2, point 4) : information non medicale rattachee
 * au patient. Un accompagnateur n'a ni ticket ni file d'attente propre — il
 * n'est pas pris en charge lui-meme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('relation')->nullable(); // ex. « epoux », « mere »
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companions');
    }
};
