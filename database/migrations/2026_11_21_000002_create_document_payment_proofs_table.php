<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_request_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('appointment_payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('path');
            $table->string('reference_number')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['document_request_id', 'status']);
            $table->index(['appointment_payment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_payment_proofs');
    }
};
