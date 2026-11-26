<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            // Existing requests keep their original fulfillment rules; only new submissions require assessment.
            $table->boolean('assessment_required')->default(false);
            $table->string('assessment_status', 20)->nullable();
            $table->text('requirements_notes')->nullable();
            $table->decimal('assessed_fee', 10, 2)->nullable();
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assessed_at')->nullable();
            $table->unsignedInteger('assessment_version')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropForeign(['assessed_by']);
            $table->dropColumn(['assessment_required', 'assessment_status', 'requirements_notes', 'assessed_fee', 'assessed_by', 'assessed_at', 'assessment_version']);
        });
    }
};
