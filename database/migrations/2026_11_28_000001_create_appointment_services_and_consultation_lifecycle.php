<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_services', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->json('eligible_roles');
            $table->boolean('is_active')->default(false);
            $table->boolean('requires_approval')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $examples = [
            ['general-registrar-consultation', 'General Registrar Consultation / Advising', 'Request an in-person Registrar consultation.', ['student', 'alumni'], true, true, 1],
            ['enrollment-assistance', 'Enrollment Assistance', null, [], false, true, 2],
            ['academic-record-correction', 'Academic Record Correction', null, [], false, true, 3],
            ['subject-crediting-consultation', 'Subject Crediting Consultation', null, [], false, true, 4],
            ['subject-section-concerns', 'Subject and Section Concerns', null, [], false, true, 5],
            ['admission-consultation', 'Admission Consultation', null, [], false, true, 6],
            ['graduation-clearance-consultation', 'Graduation Clearance Consultation', null, [], false, true, 7],
            ['academic-record-consultation', 'Academic Record Consultation', null, [], false, true, 8],
            ['submission-of-original-requirements', 'Submission of Original Requirements', null, [], false, true, 9],
        ];

        foreach ($examples as [$slug, $name, $description, $roles, $active, $approval, $order]) {
            DB::table('appointment_services')->insert([
                'slug' => $slug, 'name' => $name, 'description' => $description,
                'eligible_roles' => json_encode($roles), 'is_active' => $active,
                'requires_approval' => $approval, 'sort_order' => $order,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('appointment_service_id')->nullable()->after('user_id')->constrained('appointment_services')->restrictOnDelete();
            $table->string('reference_number', 50)->nullable()->unique();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('checked_in_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->text('staff_remarks')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'checked_in', 'completed', 'cancelled', 'rejected', 'no_show'])->default('pending')->change();
        });

        $serviceId = DB::table('appointment_services')->where('slug', 'general-registrar-consultation')->value('id');
        foreach (DB::table('appointments')->where('visit_type', 'advising')->orderBy('id')->get(['id']) as $appointment) {
            DB::table('appointments')->where('id', $appointment->id)->update([
                'appointment_service_id' => $serviceId,
                'reference_number' => 'DTC-ADV-'.str_pad((string) $appointment->id, 8, '0', STR_PAD_LEFT),
            ]);
        }
    }

    public function down(): void
    {
        if (DB::table('appointments')->whereNotNull('appointment_service_id')->exists()
            || DB::table('appointments')->whereIn('status', ['checked_in', 'rejected', 'no_show'])->exists()) {
            throw new RuntimeException('Appointment service or lifecycle data exists. Preserve it and use an application-only rollback.');
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->default('pending')->change();
            $table->dropConstrainedForeignId('checked_in_by');
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropConstrainedForeignId('appointment_service_id');
            $table->dropUnique(['reference_number']);
            $table->dropColumn(['reference_number', 'confirmed_at', 'checked_in_at', 'cancellation_reason', 'rejection_reason', 'staff_remarks']);
        });
        Schema::dropIfExists('appointment_services');
    }
};
