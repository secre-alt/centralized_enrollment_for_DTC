<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('lifecycle_version')->default(0);
            $table->foreignId('previous_appointment_id')->nullable()->unique()->constrained('appointments')->restrictOnDelete();
        });
        Schema::table('appointment_document_items', function (Blueprint $table) {
            $table->string('resolution', 20)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
        });
        Schema::create('appointment_lifecycle_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->uuid('operation_key')->unique();
            $table->char('request_fingerprint', 64);
            $table->string('action', 60);
            $table->unsignedBigInteger('previous_version')->nullable();
            $table->unsignedBigInteger('resulting_version');
            $table->json('document_item_ids');
            $table->json('before');
            $table->json('after');
            $table->json('outcome');
            $table->text('reason')->nullable();
            $table->timestamp('created_at');
            $table->index(['appointment_id', 'id']);
        });
        DB::unprepared("CREATE TRIGGER appointment_lifecycle_events_no_update BEFORE UPDATE ON appointment_lifecycle_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Lifecycle events are append-only'");
        DB::unprepared("CREATE TRIGGER appointment_lifecycle_events_no_delete BEFORE DELETE ON appointment_lifecycle_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Lifecycle events are append-only'");
    }

    public function down(): void
    {
        if (DB::table('appointment_lifecycle_events')->exists()
            || DB::table('appointments')->where('lifecycle_version', '>', 0)->orWhereNotNull('previous_appointment_id')->exists()
            || DB::table('appointment_document_items')->whereNotNull('resolution')->orWhereNotNull('resolved_at')->orWhereNotNull('resolved_by')->exists()) {
            throw new RuntimeException('Lifecycle integrity data exists. Preserve it and use an application-only rollback.');
        }
        DB::unprepared('DROP TRIGGER IF EXISTS appointment_lifecycle_events_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS appointment_lifecycle_events_no_delete');
        Schema::dropIfExists('appointment_lifecycle_events');
        Schema::table('appointment_document_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropColumn(['resolution', 'resolved_at']);
        });
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('previous_appointment_id');
            $table->dropColumn('lifecycle_version');
        });
    }
};
