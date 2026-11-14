<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Appointment;
use App\Models\AppointmentPayment;
use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class ExpandedListFiltersTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function document($user, array $attributes = []): DocumentRequest
    {
        $document = DocumentRequest::create(array_merge([
            'user_id' => $user->id, 'document_type' => 'tor', 'copies' => 1,
            'status' => 'submitted', 'payment_status' => 'unpaid', 'payment_method' => 'walk_in', 'fee' => 50,
        ], $attributes));
        $document->created_at = '2026-01-12 23:59:59';
        $document->save();

        return $document;
    }

    public function test_review_filters_keep_only_verified_pending_enrollments_and_preserve_pagination(): void
    {
        $this->actingAs($this->makeUserWithRole('registrar'));
        $base = ['status' => 'pending', 'is_paid' => true, 'school_year' => '2025-2026', 'year_level' => 2, 'semester' => 2, 'is_irregular' => true, 'created_at' => '2026-01-12 23:59:59'];
        $target = Enrollment::factory()->create($base);
        Payment::factory()->create(['enrollment_id' => $target->id]);
        for ($i = 0; $i < 5; $i++) {
            $row = Enrollment::factory()->create($base + ['program_id' => $target->program_id]);
            Payment::factory()->create(['enrollment_id' => $row->id]);
        }
        foreach ([['status' => 'approved'], ['is_paid' => false], ['is_irregular' => false], ['semester' => 1]] as $different) {
            $row = Enrollment::factory()->create(array_merge($base, ['program_id' => $target->program_id], $different));
            Payment::factory()->create(['enrollment_id' => $row->id]);
        }
        $filters = ['program_id' => $target->program_id, 'school_year' => '2025-2026', 'year_level' => 2, 'semester' => 2, 'classification' => 'irregular', 'date_from' => '2026-01-12', 'date_to' => '2026-01-12', 'per_page' => 5];
        $response = $this->get(route('registrar.enrollments.index', $filters))->assertOk()
            ->assertViewHas('enrollments', fn ($rows) => $rows->total() === 6 && $rows->count() === 5);
        $response->assertSee('classification=irregular')->assertSee('date_to=2026-01-12')->assertSee('collapse show', false);
        $this->get(route('registrar.enrollments.index', $filters + ['search' => $target->user->email]))->assertOk()
            ->assertViewHas('enrollments', fn ($rows) => $rows->total() === 1);
        $this->get(route('registrar.enrollments.index', ['date_to' => '2026-01-11']))->assertOk()
            ->assertViewHas('enrollments', fn ($rows) => $rows->total() === 0);
    }

    public function test_document_payment_filters_apply_to_bundles_once_and_counts_respect_other_filters(): void
    {
        $cashier = $this->makeUserWithRole('cashier');
        $owner = $this->makeUserWithRole('alumni', ['name' => 'Specific Requester']);
        $standalone = $this->document($owner, ['payment_method' => 'gcash', 'payment_status' => 'pending_verification']);
        $this->document($owner, ['document_type' => 'diploma']);
        $appointment = Appointment::factory()->create(['user_id' => $owner->id]);
        $this->document($owner, ['appointment_id' => $appointment->id]);
        $this->document($owner, ['appointment_id' => $appointment->id]);
        $bundle = AppointmentPayment::factory()->create(['appointment_id' => $appointment->id, 'is_document_bundle' => true, 'payment_method' => 'gcash', 'status' => 'pending', 'created_at' => '2026-01-12 00:00:00']);
        $params = ['search' => 'Specific Requester', 'method' => 'gcash', 'document_type' => 'tor', 'date_from' => '2026-01-12', 'date_to' => '2026-01-12'];
        $response = $this->actingAs($cashier)->get(route('cashier.document-payments.index', $params))->assertOk();
        $this->assertSame([$standalone->id], $response->viewData('requests')->pluck('id')->all());
        $this->assertSame([$bundle->id], $response->viewData('bundles')->pluck('id')->all());
        $this->assertSame(2, $response->viewData('counts')['pending_verification']);
        $this->get(route('cashier.document-payments.index', $params + ['status' => 'verified']))->assertOk()
            ->assertViewHas('requests', fn ($rows) => $rows->isEmpty())
            ->assertViewHas('bundles', fn ($rows) => $rows->isEmpty())
            ->assertViewHas('counts', fn ($counts) => $counts['pending_verification'] === 2);
        $this->get(route('cashier.document-payments.index', ['date_to' => '2026-01-11']))->assertOk()
            ->assertViewHas('bundles', fn ($rows) => $rows->isEmpty());
    }

    public function test_audit_filters_combine_actor_action_search_and_inclusive_dates(): void
    {
        $admin = $this->makeUserWithRole('admin');
        $other = $this->makeUserWithRole('cashier');
        foreach ([$admin, $other] as $actor) {
            $log = AuditLog::create(['user_id' => $actor->id, 'action' => 'payment.verify', 'description' => 'Unique audit match']);
            $log->created_at = '2026-01-12 23:59:59';
            $log->save();
        }
        $this->actingAs($admin)->get(route('admin.settings.audit', ['actor_id' => $admin->id, 'action' => 'payment.verify', 'search' => 'Unique audit match', 'date_from' => '2026-01-12', 'date_to' => '2026-01-12']))->assertOk()
            ->assertViewHas('logs', fn ($rows) => $rows->total() === 1 && $rows->first()->user_id === $admin->id)
            ->assertSee('audit-actor_id');
        $this->get(route('admin.settings.audit', ['date_to' => '2026-01-11']))->assertOk()
            ->assertViewHas('logs', fn ($rows) => $rows->total() === 0);
    }

    public function test_application_dates_combine_with_existing_filters_in_normal_and_ajax_requests(): void
    {
        $program = Program::factory()->create();
        $application = Application::create(['reference_no' => 'FILTER-APP', 'program_id' => $program->id, 'academic_status' => 'new_student', 'first_name' => 'Date', 'last_name' => 'Applicant', 'email' => 'date@example.test', 'status' => 'submitted']);
        $application->created_at = '2026-01-12 23:59:59';
        $application->save();
        $this->actingAs($this->makeUserWithRole('registrar'));
        $params = ['program_id' => $program->id, 'type' => 'new_student', 'status' => 'submitted', 'search' => 'FILTER-APP', 'date_from' => '2026-01-12', 'date_to' => '2026-01-12'];
        foreach ([[], ['X-Requested-With' => 'XMLHttpRequest']] as $headers) {
            $this->get(route('registrar.applications.index', $params), $headers)->assertOk()
                ->assertViewHas('applications', fn ($rows) => $rows->total() === 1);
        }
        $this->get(route('registrar.applications.index', ['date_to' => '2026-01-11']))->assertOk()
            ->assertViewHas('applications', fn ($rows) => $rows->total() === 0);
    }

    public function test_request_history_filters_remain_owner_scoped_with_secondary_staff_role(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        $owner->assignRole('cashier');
        $target = $this->document($owner, ['purpose' => 'Unique purpose']);
        $this->document($owner, ['purpose' => 'Unique purpose', 'document_type' => 'diploma']);
        $this->document($this->makeUserWithRole('alumni'), ['purpose' => 'Unique purpose']);
        $this->actingAs($owner)->get(route('portal.documents.index', ['document_type' => 'tor', 'search' => 'Unique purpose', 'date_from' => '2026-01-12', 'date_to' => '2026-01-12']))->assertOk()
            ->assertViewHas('requests', fn ($rows) => $rows->pluck('id')->all() === [$target->id])
            ->assertViewHas('totalRequests', 1);
        $this->get(route('portal.documents.index', ['date_to' => '2026-01-11']))->assertOk()
            ->assertSee('No requests match your filters');
    }

    public function test_invalid_ranges_filters_and_role_boundaries_are_enforced(): void
    {
        foreach ([['registrar', 'registrar.enrollments.index'], ['registrar', 'registrar.applications.index'], ['cashier', 'cashier.document-payments.index'], ['admin', 'admin.settings.audit'], ['alumni', 'portal.documents.index']] as [$role, $route]) {
            $this->actingAs($this->makeUserWithRole($role));
            foreach ([['date_from' => 'bad'], ['date_to' => 'bad'], ['date_from' => '2026-02-01', 'date_to' => '2026-01-01']] as $invalid) {
                $this->getJson(route($route, $invalid))->assertUnprocessable();
            }
        }
        $this->actingAs($this->makeUserWithRole('student'));
        foreach (['registrar.enrollments.index', 'registrar.applications.index', 'cashier.document-payments.index', 'admin.settings.audit'] as $route) {
            $this->get(route($route, ['date_to' => '2026-01-12']))->assertForbidden();
        }
    }
}
