<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('duplicate_review_outcome', 30)->nullable()->after('remarks');
            $table->foreignId('duplicate_reviewed_by')->nullable()->after('duplicate_review_outcome')->constrained('users')->nullOnDelete();
            $table->timestamp('duplicate_reviewed_at')->nullable()->after('duplicate_reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['duplicate_reviewed_by']);
            $table->dropColumn(['duplicate_review_outcome', 'duplicate_reviewed_by', 'duplicate_reviewed_at']);
        });
    }
};
