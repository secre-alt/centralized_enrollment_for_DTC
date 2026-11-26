<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('visit_type', 20)->default('legacy');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->index(['visit_type', 'status']);
        });

        Schema::table('document_requests', function (Blueprint $table) {
            $table->foreignId('pending_claim_appointment_id')->nullable()->constrained('appointments')->restrictOnDelete();
        });

        Schema::create('appointment_document_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_request_id')->constrained()->restrictOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['appointment_id', 'document_request_id'], 'appointment_document_items_visit_document_unique');
        });

        Schema::create('appointment_disruptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $table->foreignId('office_closure_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('open');
            $table->string('notification_key', 120)->unique();
            $table->string('notification_status', 20)->default('pending');
            $table->unsignedInteger('notification_attempts')->default(0);
            $table->timestamp('notification_available_at')->nullable();
            $table->text('notification_last_error')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['appointment_id', 'office_closure_id'], 'appointment_disruptions_visit_closure_unique');
            $table->index(['notification_status', 'notification_available_at'], 'appointment_disruptions_notification_retry_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_disruptions');
        Schema::dropIfExists('appointment_document_items');
        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pending_claim_appointment_id');
        });
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('completed_by');
            $table->dropIndex(['visit_type', 'status']);
            $table->dropColumn(['visit_type', 'completed_at']);
        });
    }
};
