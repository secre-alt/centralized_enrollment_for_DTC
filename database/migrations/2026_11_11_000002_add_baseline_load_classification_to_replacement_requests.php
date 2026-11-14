<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollment_replacement_requests', function (Blueprint $table) {
            $table->boolean('baseline_is_irregular')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('enrollment_replacement_requests', function (Blueprint $table) {
            $table->dropColumn('baseline_is_irregular');
        });
    }
};
