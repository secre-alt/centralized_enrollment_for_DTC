<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy MySQL first-TIMESTAMP behavior may add ON UPDATE implicitly.
        // Refund reconciliation must never change the original collection date.
        DB::statement('ALTER TABLE payments MODIFY paid_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }

    public function down(): void
    {
        // Keep collection timestamps protected; rollback must not restore the
        // legacy automatic rewrite of financial history.
    }
};
