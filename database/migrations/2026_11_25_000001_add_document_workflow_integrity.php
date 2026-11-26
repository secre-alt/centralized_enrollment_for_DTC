<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->restrictOnDelete();
        });
        Schema::create('document_workflow_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->uuid('operation_key')->unique();
            $table->char('request_fingerprint', 64);
            $table->string('action', 60);
            $table->json('document_request_ids');
            $table->json('before');
            $table->json('after');
            $table->json('outcome');
            $table->timestamp('created_at');
            $table->index(['action', 'id']);
        });
        DB::unprepared("CREATE TRIGGER document_workflow_events_no_update BEFORE UPDATE ON document_workflow_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Document workflow events are append-only'");
        DB::unprepared("CREATE TRIGGER document_workflow_events_no_delete BEFORE DELETE ON document_workflow_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Document workflow events are append-only'");
    }

    public function down(): void
    {
        if (DB::table('document_workflow_events')->exists()
            || DB::table('document_requests')->whereNotNull('released_at')->orWhereNotNull('released_by')->exists()) {
            throw new RuntimeException('Document workflow history exists. Preserve it and use an application-only rollback.');
        }
        DB::unprepared('DROP TRIGGER IF EXISTS document_workflow_events_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS document_workflow_events_no_delete');
        Schema::dropIfExists('document_workflow_events');
        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('released_by');
            $table->dropColumn('released_at');
        });
    }
};
