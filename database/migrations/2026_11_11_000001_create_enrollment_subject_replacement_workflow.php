<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_subjects', function (Blueprint $table) {
            $table->string('classification')->default('unclassified')->after('subject_name');
        });

        Schema::table('class_schedules', function (Blueprint $table) {
            $table->foreignId('hosting_program_id')
                ->nullable()
                ->after('course_subject_id')
                ->constrained('programs')
                ->restrictOnDelete();
            $table->string('section_code', 30)->nullable()->after('course_subject_id');
            $table->string('school_year', 20)->nullable()->after('section_code');
            $table->unsignedTinyInteger('semester')->nullable()->after('school_year');
            $table->unsignedSmallInteger('capacity')->nullable()->after('semester');
            $table->boolean('is_active')->default(true)->after('capacity');
            $table->boolean('requires_irregular')->nullable()->after('is_active');
            $table->index('course_subject_id', 'class_schedules_course_subject_lookup_index');
            $table->index(['school_year', 'semester', 'is_active'], 'class_schedules_term_active_index');
            $table->unique(
                ['course_subject_id', 'section_code', 'school_year', 'semester'],
                'class_schedules_section_term_unique'
            );
        });

        Schema::create('course_subject_equivalencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_subject_id')->constrained('course_subjects')->restrictOnDelete();
            $table->foreignId('equivalent_subject_id')->constrained('course_subjects')->restrictOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->text('approval_reason');
            $table->boolean('requires_irregular');
            $table->timestamp('approved_at');
            $table->timestamps();
            $table->unique(
                ['source_subject_id', 'equivalent_subject_id'],
                'course_subject_equivalencies_pair_unique'
            );
        });

        Schema::create('enrollment_replacement_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('open');
            $table->text('reason');
            $table->text('student_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['enrollment_id', 'status'], 'enrollment_replacement_requests_state_index');
        });

        Schema::create('enrollment_replacement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')
                ->constrained('enrollment_replacement_requests')
                ->cascadeOnDelete();
            $table->foreignId('original_subject_id')
                ->constrained('course_subjects')
                ->restrictOnDelete();
            $table->foreignId('original_schedule_id')
                ->nullable()
                ->constrained('class_schedules')
                ->nullOnDelete();
            $table->foreignId('selected_schedule_id')
                ->nullable()
                ->constrained('class_schedules')
                ->nullOnDelete();
            $table->foreignId('equivalency_id')
                ->nullable()
                ->constrained('course_subject_equivalencies')
                ->nullOnDelete();
            $table->timestamps();
            $table->unique(['request_id', 'original_subject_id'], 'enrollment_replacement_items_subject_unique');
        });

        Schema::create('enrollment_subject_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('curriculum_subject_id')
                ->constrained('course_subjects')
                ->restrictOnDelete();
            $table->foreignId('class_schedule_id')
                ->constrained('class_schedules')
                ->restrictOnDelete();
            $table->timestamps();
            $table->unique(
                ['enrollment_id', 'curriculum_subject_id'],
                'enrollment_subject_offerings_subject_unique'
            );
            $table->index(['class_schedule_id', 'enrollment_id'], 'enrollment_subject_offerings_capacity_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_subject_offerings');
        Schema::dropIfExists('enrollment_replacement_items');
        Schema::dropIfExists('enrollment_replacement_requests');
        Schema::dropIfExists('course_subject_equivalencies');

        // Keep the standalone subject index: the pre-existing foreign key needs it when the composite index is removed.
        Schema::table('class_schedules', function (Blueprint $table) {
            $table->dropUnique('class_schedules_section_term_unique');
            $table->dropIndex('class_schedules_term_active_index');
            $table->dropForeign(['hosting_program_id']);
            $table->dropColumn([
                'section_code',
                'school_year',
                'semester',
                'capacity',
                'is_active',
                'requires_irregular',
                'hosting_program_id',
            ]);
        });

        Schema::table('course_subjects', function (Blueprint $table) {
            $table->dropColumn('classification');
        });
    }
};
