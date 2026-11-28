<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\DocumentRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class AppointmentEditingSafetyTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function booking(string $status = 'unpaid'): array
    {
        $user = $this->makeUserWithRole('student');
        $appointment = Appointment::factory()->create(['user_id' => $user->id, 'status' => 'pending', 'document_type' => 'transcript']);
        $document = DocumentRequest::create([
            'user_id' => $user->id, 'appointment_id' => $appointment->id, 'document_type' => 'tor',
            'copies' => 2, 'fee' => 80, 'status' => 'submitted', 'is_paid' => $status === 'verified',
            'payment_method' => 'gcash_qr', 'payment_status' => $status,
        ]);
        $this->actingAs($user);
        return [$appointment, $document];
    }

    private function payload(Appointment $appointment, string $type = 'transcript'): array
    {
        return ['appointment_slot_id' => $appointment->appointment_slot_id, 'document_type' => $type, 'purpose' => 'Updated purpose'];
    }

    public function test_legacy_document_booking_edit_routes_are_read_only_and_preserve_payment(): void
    {
        foreach (['unpaid', 'pending_verification', 'verified', 'rejected'] as $status) {
            [$appointment, $document] = $this->booking($status);
            $this->get(route('portal.appointments.edit', $appointment))->assertStatus(410);
            $this->put(route('portal.appointments.update', $appointment), $this->payload($appointment))->assertStatus(410);
            $this->post(route('portal.appointments.cancel', $appointment))->assertStatus(410);
            $this->assertSame('80.00', $document->fresh()->fee);
            $this->assertSame($status, $document->fresh()->payment_status);
            $this->assertSame('pending', $appointment->fresh()->status);
        }
    }

    public function test_progressing_or_completed_documents_are_unchanged_by_legacy_update_attempts(): void
    {
        foreach (['processing', 'ready', 'released'] as $status) {
            [$appointment, $document] = $this->booking('verified');
            $document->update(['status' => $status, 'purpose' => 'Original purpose']);

            $this->put(route('portal.appointments.update', $appointment), $this->payload($appointment, 'diploma'))->assertStatus(410);

            $document->refresh();
            $this->assertSame('tor', $document->document_type);
            $this->assertSame('Original purpose', $document->purpose);
            $this->assertSame('80.00', $document->fee);
            $this->assertSame('transcript', $appointment->fresh()->document_type);
        }
    }

    public function test_legacy_update_attempt_does_not_modify_any_document_in_a_mixed_bundle(): void
    {
        [$appointment, $submitted] = $this->booking();
        $submitted->update(['purpose' => 'Original submitted purpose']);
        $ready = DocumentRequest::create([
            'user_id' => $appointment->user_id,
            'appointment_id' => $appointment->id,
            'document_type' => 'diploma',
            'copies' => 1,
            'fee' => 100,
            'status' => 'ready',
            'purpose' => 'Ready document purpose',
            'payment_method' => 'walk_in',
            'payment_status' => 'unpaid',
        ]);

        $this->put(route('portal.appointments.update', $appointment), $this->payload($appointment))->assertStatus(410);

        $this->assertSame('Original submitted purpose', $submitted->fresh()->purpose);
        $this->assertSame('Ready document purpose', $ready->fresh()->purpose);
    }

    public function test_appointment_history_has_no_document_edit_or_reschedule_controls(): void
    {
        [, $document] = $this->booking('unpaid');
        $this->get(route('portal.appointments.index'))
            ->assertOk()
            ->assertSee('Appointment history')
            ->assertSee('Document progress and release details')
            ->assertSee('Document Requests')
            ->assertDontSee('dr-edit-modal')
            ->assertDontSee('Edit booking')
            ->assertDontSee('Ready for pickup');

        $document->update(['payment_status' => 'pending_verification']);
        $this->get(route('portal.appointments.index'))
            ->assertOk()
            ->assertSee('Payment History')
            ->assertDontSee('name="appointment_slot_id"', false);
    }

    public function test_legacy_booking_updates_are_read_only_for_every_existing_status(): void
    {
        foreach (['confirmed', 'cancelled'] as $status) {
            [$appointment, $document] = $this->booking();
            Appointment::whereKey($appointment->id)->update(['status' => $status]);
            $this->put(route('portal.appointments.update', $appointment), $this->payload($appointment))->assertStatus(410);
            $this->assertSame($status, $appointment->fresh()->status);
            $this->assertNotSame('Updated purpose', $document->fresh()->purpose);
        }
    }

    public function test_passed_schedule_is_shown_as_read_only_history_without_pickup_rescheduling(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-28 09:00:00'));
        try {
            [$appointment, $document] = $this->booking('verified');
            $appointment->slot->update(['date' => '2026-10-28', 'start_time' => '08:00:00', 'end_time' => '08:30:00']);
            $this->assertTrue($appointment->fresh()->scheduleHasPassed());
            $this->get(route('portal.appointments.index'))->assertOk()
                ->assertSee('Past appointment')
                ->assertDontSee('choose a new time')
                ->assertDontSee('Edit booking')
                ->assertDontSee('Ready for pickup');
            $this->assertSame('pending', $appointment->fresh()->status);
            $this->assertSame('verified', $document->fresh()->payment_status);
            $this->assertSame('80.00', $document->fresh()->fee);

            $appointment->update(['status' => 'confirmed']);
            $this->get(route('portal.appointments.index'))->assertOk()
                ->assertSee('Past appointment')
                ->assertDontSee('Need to reschedule')
                ->assertDontSee('another pickup time')
                ->assertDontSee('data-registrar-contact', false);

            $appointment->update(['status' => 'cancelled']);
            $this->assertFalse($appointment->fresh()->scheduleHasPassed());
        } finally {
            $this->travelBack();
        }
    }

    public function test_schedule_passes_at_end_time_not_start_time(): void
    {
        [$appointment] = $this->booking();
        $appointment->slot->update(['date' => '2026-10-28', 'start_time' => '08:00:00', 'end_time' => '08:30:00']);
        try {
            $this->travelTo(\Carbon\Carbon::parse('2026-10-28 08:15:00'));
            $this->assertFalse($appointment->fresh()->scheduleHasPassed());
            $this->travelTo(\Carbon\Carbon::parse('2026-10-28 08:30:00'));
            $this->assertTrue($appointment->fresh()->scheduleHasPassed());
        } finally {
            $this->travelBack();
        }
    }

    public function test_payment_details_are_not_repeated_in_appointment_history(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-28 09:00:00'));
        try {
            [$overdue] = $this->booking('unpaid');
            $overdue->update(['status' => 'confirmed']);
            $overdue->slot->update(['date' => '2026-10-27', 'start_time' => '08:00:00', 'end_time' => '08:30:00']);
            $this->get(route('portal.appointments.index'))->assertOk()
                ->assertSee('Payment history')
                ->assertDontSee('Review payment')
                ->assertDontSee('Payment details');

            [$cancelled, $cancelledDocument] = $this->booking('unpaid');
            $cancelled->update(['status' => 'cancelled']);
            $this->get(route('portal.appointments.index'))->assertOk()->assertDontSee('Review payment');

            $cancelledDocument->update(['fee' => 0]);
            $this->get(route('portal.appointments.index'))->assertOk()
                ->assertDontSee('No Payment Required')
                ->assertDontSee('Review payment');

            [$paidCancelled] = $this->booking('verified');
            $paidCancelled->update(['status' => 'cancelled']);
            $this->get(route('portal.appointments.index'))->assertOk()
                ->assertDontSee('Review payment')
                ->assertDontSee('Payment details');
        } finally {
            $this->travelBack();
        }
    }

    public function test_appointment_history_does_not_show_legacy_pickup_contact_controls(): void
    {
        [$appointment] = $this->booking();
        $this->get(route('portal.appointments.index'))->assertOk()
            ->assertDontSee('data-booking-reference', false);

        $appointment->update(['status' => 'confirmed']);
        $this->get(route('portal.appointments.index'))->assertOk()
            ->assertSee('Appointment history')
            ->assertDontSee('Need to change or cancel?')
            ->assertDontSee('data-registrar-contact', false)
            ->assertDontSee('another pickup time');

        $this->get(route('portal.appointments.index'))->assertOk()
            ->assertDontSee('registrar@example.test')
            ->assertDontSee('data-booking-reference', false)
            ->assertDontSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_advising_cancellation_stays_available_and_document_history_cannot_be_cancelled(): void
    {
        foreach (['verified', 'pending_verification'] as $status) {
            [$appointment, $document] = $this->booking($status);
            $this->post(route('portal.appointments.cancel', $appointment))->assertStatus(410);
            $this->assertSame($status, $document->fresh()->payment_status);
            $this->assertSame('submitted', $document->fresh()->status);
            $this->assertSame('pending', $appointment->fresh()->status);
        }

        $user = $this->makeUserWithRole('student');
        $slot = \App\Models\AppointmentSlot::factory()->create(['date' => now()->addDays(2)->toDateString()]);
        $advising = Appointment::factory()->create([
            'user_id' => $user->id,
            'appointment_slot_id' => $slot->id,
            'visit_type' => 'advising',
            'status' => 'pending',
            'document_type' => 'advising',
        ]);
        $this->actingAs($user)->post(route('portal.appointments.cancel', $advising), [
            'operation_key' => (string) \Illuminate\Support\Str::uuid(),
            'lifecycle_version' => 0,
        ])->assertSessionHasNoErrors()->assertRedirect(route('portal.advising.index'));
        $this->assertSame('cancelled', $advising->fresh()->status);
    }
}
