<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('refund_status')->default('not_required')->after('status');
            $table->decimal('refund_amount', 8, 2)->nullable()->after('refund_status');
            $table->string('refund_reference')->nullable()->after('refund_status');
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete()->after('refund_reference');
            $table->timestamp('refunded_at')->nullable()->after('refunded_by');
        });

        DB::table('payments')
            ->join('enrollments', 'enrollments.id', '=', 'payments.enrollment_id')
            ->where('payments.status', 'verified')
            ->where('enrollments.status', 'rejected')
            ->update(['payments.refund_status' => 'pending']);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['refunded_by']);
            $table->dropColumn(['refund_status', 'refund_amount', 'refund_reference', 'refunded_by', 'refunded_at']);
        });
    }
};
