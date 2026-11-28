<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\AppointmentSlot;
use App\Models\DocumentRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\SeedsRoles;
use Tests\TestCase;

class AppointmentServiceWorkflowTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('dtc_testing', DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->seedRoles();
    }

    private function command(Appointment $appointment): array
    {
        return ['operation_key' => (string) Str::uuid(), 'lifecycle_version' => $appointment->fresh()->lifecycle_version];
    }

    private function slot(array $attributes = []): AppointmentSlot
    {
        return AppointmentSlot::factory()->create($attributes + [
            'date' => now()->addDays(3)->toDateString(), 'status' => 'open', 'max_bookings' => 2,
        ]);
    }

    private function book($owner, AppointmentService $service, AppointmentSlot $slot): Appointment
    {
        $this->actingAs($owner)->post(route('portal.advising.store'), [
            'appointment_service_id' => $service->id, 'appointment_slot_id' => $slot->id, 'purpose' => 'Need help reviewing my enrollment.',
        ])->assertSessionHasNoErrors()->assertRedirect(route('portal.advising.index'));

        return Appointment::where('user_id', $owner->id)->latest('id')->firstOrFail();
    }

    public function test_default_service_is_student_alumni_only_pending_and_free(): void
    {
        $service = AppointmentService::where('slug', 'general-registrar-consultation')->firstOrFail();
        $this->assertTrue($service->is_active);
        $this->assertTrue($service->requires_approval);
        $this->assertSame(['student', 'alumni'], $service->eligible_roles);
        $this->assertSame(8, AppointmentService::where('is_active', false)->count());

        $owner = $this->makeUserWithRole('student');
        $appointment = $this->book($owner, $service, $this->slot());
        $this->assertSame('pending', $appointment->status);
        $this->assertNotEmpty($appointment->reference_number);
        $this->assertSame($service->id, $appointment->appointment_service_id);
        $this->assertDatabaseCount('document_requests', 0);
        $this->assertDatabaseCount('appointment_payments', 0);
        $this->actingAs($owner)->get(route('portal.appointments.create'))->assertOk()
            ->assertSee(route('portal.documents.index'), false);

        $this->actingAs($this->makeUserWithRole('registrar'))
            ->get(route('registrar.appointments.index', ['status' => 'all', 'from' => now()->addDays(2)->toDateString(), 'to' => now()->addDays(4)->toDateString()]))
            ->assertOk()->assertSee('Consultation summary')->assertSee($service->name)->assertSee('1', false);
        $this->get(route('registrar.appointments.index', ['kind' => 'historical']))
            ->assertOk()->assertSee('kind=historical&amp;status=pending', false);
        $this->get(route('registrar.appointments.index', ['status' => 'all']))
            ->assertOk()->assertSee('registrar-appointments-index-remarks-'.$appointment->id, false);
        $this->get(route('registrar.appointments.index', ['from' => 'invalid']))->assertSessionHasErrors('from');
    }

    public function test_service_eligibility_and_active_state_are_enforced_on_the_server(): void
    {
        $applicant = $this->makeUserWithRole('new_applicant');
        $service = AppointmentService::where('slug', 'general-registrar-consultation')->firstOrFail();
        $slot = $this->slot();

        $this->actingAs($applicant)->post(route('portal.advising.store'), [
            'appointment_service_id' => $service->id, 'appointment_slot_id' => $slot->id, 'purpose' => 'Question',
        ])->assertForbidden();
        $this->assertDatabaseCount('appointments', 0);

        $service->update(['is_active' => false]);
        $student = $this->makeUserWithRole('student');
        $this->actingAs($student)->post(route('portal.advising.store'), [
            'appointment_service_id' => $service->id, 'appointment_slot_id' => $slot->id, 'purpose' => 'Question',
        ])->assertNotFound();
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_registrar_can_configure_examples_without_deleting_history(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $service = AppointmentService::where('slug', 'enrollment-assistance')->firstOrFail();
        $this->actingAs($registrar)->get(route('registrar.appointments.services.index'))->assertOk()->assertSee($service->name);
        $this->put(route('registrar.appointments.services.update', $service), [
            'name' => $service->name, 'description' => 'Enrollment questions.', 'instructions' => 'Bring your records.',
            'eligible_roles' => ['student', 'alumni'], 'is_active' => '1', 'requires_approval' => '1', 'sort_order' => 2,
        ])->assertSessionHasNoErrors();

        $this->assertTrue($service->fresh()->is_active);
        $this->assertSame(['student', 'alumni'], $service->fresh()->eligible_roles);
        $this->actingAs($this->makeUserWithRole('cashier'))->get(route('registrar.appointments.services.index'))->assertForbidden();
    }

    public function test_automatic_confirmation_reschedule_rejection_and_cancellation_keep_lifecycle_identity(): void
    {
        $student = $this->makeUserWithRole('student');
        $service = AppointmentService::where('slug', 'general-registrar-consultation')->firstOrFail();
        $service->update(['requires_approval' => false]);
        $firstSlot = $this->slot();
        $appointment = $this->book($student, $service, $firstSlot);
        $this->assertSame('confirmed', $appointment->status);

        $reference = $appointment->reference_number;
        $newSlot = $this->slot(['date' => now()->addDays(4)->toDateString()]);
        $this->actingAs($student)->put(route('portal.appointments.update', $appointment), $this->command($appointment) + [
            'appointment_slot_id' => $newSlot->id, 'purpose' => 'Updated concern.',
        ])->assertSessionHasNoErrors()->assertRedirect(route('portal.advising.index'));
        $appointment->refresh();
        $this->assertSame($newSlot->id, $appointment->appointment_slot_id);
        $this->assertSame($reference, $appointment->reference_number);
        $this->assertSame(2, $appointment->lifecycle_version);

        $this->actingAs($this->makeUserWithRole('registrar'))->post(route('registrar.appointments.cancel', $appointment), $this->command($appointment) + [
            'remarks' => 'Office schedule changed.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $appointment->fresh()->status);
        $this->assertSame('Office schedule changed.', $appointment->fresh()->cancellation_reason);
        $this->assertSame(3, $appointment->fresh()->lifecycle_version);
        $this->assertDatabaseCount('document_requests', 0);
        $this->assertDatabaseCount('appointment_payments', 0);
    }

    public function test_reject_check_in_completion_and_staff_remarks_enforce_transitions_and_privacy(): void
    {
        $this->travelTo(now()->copy()->setTime(8, 0));
        $student = $this->makeUserWithRole('student');
        $service = AppointmentService::where('slug', 'general-registrar-consultation')->firstOrFail();
        $slot = $this->slot(['date' => today()->toDateString(), 'start_time' => '09:00:00', 'end_time' => '09:30:00']);
        $appointment = $this->book($student, $service, $slot);
        $registrar = $this->makeUserWithRole('registrar');

        $this->actingAs($registrar)->post(route('registrar.appointments.reject', $appointment), $this->command($appointment) + [
            'rejection_reason' => 'Please choose a different service.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $appointment->fresh()->status);
        $this->assertSame('Please choose a different service.', $appointment->fresh()->rejection_reason);
        $this->post(route('registrar.appointments.check-in', $appointment), $this->command($appointment))->assertStatus(422);

        $appointment = $this->book($student, $service, $this->slot(['date' => today()->toDateString(), 'start_time' => '10:00:00', 'end_time' => '10:30:00']));
        $this->actingAs($registrar)->post(route('registrar.appointments.confirm', $appointment), $this->command($appointment))->assertSessionHasNoErrors();
        $appointment->refresh();
        $this->post(route('registrar.appointments.check-in', $appointment), $this->command($appointment))->assertSessionHasNoErrors();
        $appointment->refresh();
        $this->post(route('registrar.appointments.complete', $appointment), $this->command($appointment))->assertSessionHasNoErrors();
        $appointment->refresh();
        $this->assertSame('completed', $appointment->status);
        $this->assertNotNull($appointment->completed_at);
        $this->assertSame($registrar->id, $appointment->completed_by);
        $this->post(route('registrar.appointments.remarks', $appointment), $this->command($appointment) + [
            'staff_remarks' => 'Confidential staff note.',
        ])->assertSessionHasNoErrors();

        $this->actingAs($student)->get(route('portal.advising.index'))->assertDontSee('Confidential staff note.');
        $this->actingAs($registrar)->get(route('registrar.appointments.index', ['status' => 'completed']))->assertSee('Confidential staff note.');
    }

    public function test_no_show_is_only_available_after_the_scheduled_end(): void
    {
        $this->travelTo(now()->copy()->addDay()->setTime(7, 0));
        $student = $this->makeUserWithRole('student');
        $this->withSession(['last_user_activity_at' => now()->timestamp]);
        $service = AppointmentService::where('slug', 'general-registrar-consultation')->firstOrFail();
        $slot = $this->slot(['date' => today()->toDateString(), 'start_time' => '08:00:00', 'end_time' => '08:30:00']);
        $appointment = $this->book($student, $service, $slot);
        $registrar = $this->makeUserWithRole('registrar');
        $this->withSession(['last_user_activity_at' => now()->timestamp]);
        $this->actingAs($registrar)->post(route('registrar.appointments.confirm', $appointment), $this->command($appointment))->assertSessionHasNoErrors();
        $appointment->refresh();
        $this->travelTo(today()->copy()->setTime(8, 31));
        $this->assertSame('confirmed', $appointment->fresh()->status);
        $this->travelBack();
        $this->travelTo(now()->copy()->addDay()->setTime(8, 31));
        $appointment->slot->update(['date' => today()->toDateString()]);
        $this->withSession(['last_user_activity_at' => now()->timestamp]);
        $this->post(route('registrar.appointments.no-show', $appointment), $this->command($appointment))->assertSessionHasNoErrors();
        $this->assertSame('no_show', $appointment->fresh()->status);
    }
}
