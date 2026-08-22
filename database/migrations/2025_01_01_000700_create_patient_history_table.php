<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained();
            $table->enum('type', ['registration', 'consultation', 'referral_sent', 'referral_result']);
            $table->foreignId('service_id')->nullable()->constrained('services');
            $table->foreignId('doctor_id')->nullable()->constrained('doctors');
            $table->foreignId('referral_id')->nullable()->constrained('referrals');
            $table->text('description');
            $table->timestamps();

            $table->index(['patient_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_history');
    }
};
