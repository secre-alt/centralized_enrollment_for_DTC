<?php

namespace Tests\Feature;

use App\Models\CourseSubject;
use App\Models\CreditedSubject;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\SeedsRoles;
use Tests\TestCase;

class ModernizationSecurityTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    public function test_production_role_seeding_never_provisions_a_known_password_account(): void
    {
        $this->assertSame('dtc_testing', DB::connection()->getDatabaseName());
        $this->app->detectEnvironment(fn () => 'production');
        (new RoleSeeder)->run();

        $this->assertSame(6, Role::count());
        $this->assertSame(0, User::count());
    }

    public function test_local_role_seeding_remains_idempotent(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $this->seed(RoleSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->assertSame(6, Role::count());
        $this->assertSame(1, User::count());
        $this->assertTrue(User::first()->hasRole('admin'));
    }

    public function test_search_rejects_malformed_or_unbounded_queries_and_accepts_empty_queries(): void
    {
        $this->seedRoles();
        $this->actingAs($this->makeUserWithRole('student'));

        $this->getJson(route('search', ['q' => ['invalid']]))->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->getJson(route('search', ['q' => str_repeat('x', 151)]))->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->getJson(route('search', ['q' => '']))->assertOk()->assertJsonPath('results', []);
        $this->getJson(route('search', ['q' => 'Dashboard']))->assertOk()->assertJsonStructure(['results', 'query']);
    }

    public function test_account_removal_preserves_paid_enrollment_and_receipt_history(): void
    {
        $this->seedRoles();
        $owner = $this->makeUserWithRole('student');
        $enrollment = Enrollment::factory()->paid()->create(['user_id' => $owner->id]);
        $payment = Payment::factory()->create(['enrollment_id' => $enrollment->id]);
        $original = $payment->fresh()->getRawOriginal();

        $this->actingAs($this->makeUserWithRole('admin'))
            ->deleteJson(route('admin.users.destroy', $owner))->assertUnprocessable();

        $this->assertSame($original, $payment->fresh()->getRawOriginal());
        $this->assertTrue($owner->fresh()->hasRole('student'));
        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id, 'user_id' => $owner->id]);
    }

    public function test_account_removal_preserves_financial_actor_identity(): void
    {
        $this->seedRoles();
        $cashier = $this->makeUserWithRole('cashier');
        $payment = Payment::factory()->create(['verified_by' => $cashier->id, 'processed_by' => $cashier->id]);
        $original = $payment->fresh()->getRawOriginal();

        $this->actingAs($this->makeUserWithRole('admin'))
            ->deleteJson(route('admin.users.destroy', $cashier))->assertUnprocessable();

        $this->assertSame($original, $payment->fresh()->getRawOriginal());
        $this->assertTrue($cashier->fresh()->hasRole('cashier'));
    }

    public function test_unused_staff_account_can_still_be_removed(): void
    {
        $this->seedRoles();
        $cashier = $this->makeUserWithRole('cashier');
        $this->actingAs($this->makeUserWithRole('admin'))
            ->delete(route('admin.users.destroy', $cashier))->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseMissing('users', ['id' => $cashier->id]);
        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $cashier->id, 'model_type' => User::class]);
    }

    public function test_legacy_subject_identity_survives_subject_and_program_removal_attempts(): void
    {
        $this->seedRoles();
        $this->actingAs($this->makeUserWithRole('admin'));
        foreach ([false, true] as $stringIdentity) {
            $subject = CourseSubject::factory()->create();
            $enrollment = Enrollment::factory()->paid()->create([
                'subject_ids' => [$stringIdentity ? (string) $subject->id : $subject->id],
            ]);
            $this->deleteJson(route('admin.programs.subjects.destroy', $subject))->assertUnprocessable();
            $this->deleteJson(route('admin.programs.destroy', $subject->program_id))->assertUnprocessable();
            $this->assertDatabaseHas('course_subjects', ['id' => $subject->id]);
            $this->assertSame($enrollment->subject_ids, $enrollment->fresh()->subject_ids);
        }
    }

    public function test_credited_subject_documentation_survives_curriculum_removal(): void
    {
        $this->seedRoles();
        $subject = CourseSubject::factory()->create();
        $credit = CreditedSubject::create([
            'course_subject_id' => $subject->id,
            'enrollment_id' => Enrollment::factory()->create(['subject_ids' => []])->id,
            'equivalent_subject' => 'Historical external course',
        ]);
        $this->actingAs($this->makeUserWithRole('admin'))
            ->deleteJson(route('admin.programs.subjects.destroy', $subject))->assertUnprocessable();
        $this->assertDatabaseHas('credited_subjects', ['id' => $credit->id, 'course_subject_id' => $subject->id]);
    }

    public function test_unused_curriculum_subject_can_still_be_removed(): void
    {
        $this->seedRoles();
        $subject = CourseSubject::factory()->create();
        $this->actingAs($this->makeUserWithRole('admin'))
            ->delete(route('admin.programs.subjects.destroy', $subject))->assertRedirect();
        $this->assertDatabaseMissing('course_subjects', ['id' => $subject->id]);
    }
}
