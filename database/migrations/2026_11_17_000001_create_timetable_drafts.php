<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_locks', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
        });
        DB::table('timetable_locks')->insert(['id' => 1]);
        Schema::create('timetable_resources', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);
            $table->string('name', 50);
            $table->json('configuration');
            $table->timestamps();
            $table->unique(['kind', 'name']);
        });
        Schema::create('timetable_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('year_level');
            $table->string('group_code', 30);
            $table->unsignedSmallInteger('capacity');
            $table->string('school_year', 20);
            $table->unsignedTinyInteger('semester');
            $table->boolean('is_demo')->default(false);
            $table->string('status', 20)->default('draft');
            $table->json('configuration');
            $table->json('proposals')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
        Schema::table('class_schedules', function (Blueprint $table) {
            $table->foreignId('timetable_draft_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('class_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('timetable_draft_id');
        });
        Schema::dropIfExists('timetable_drafts');
        Schema::dropIfExists('timetable_resources');
        Schema::dropIfExists('timetable_locks');
    }
};
