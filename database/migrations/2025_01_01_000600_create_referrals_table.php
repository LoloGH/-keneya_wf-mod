<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('from_service_id')->constrained('services');
            $table->foreignId('to_service_id')->constrained('services');
            $table->foreignId('from_doctor_id')->constrained('doctors');
            $table->foreignId('completed_by_doctor_id')->nullable()->constrained('doctors');
            $table->text('instructions');
            $table->enum('status', ['pending', 'done'])->default('pending');
            $table->text('result_text')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['to_service_id', 'status']);
            $table->index(['from_doctor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
