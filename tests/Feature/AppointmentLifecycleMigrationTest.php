<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\DocumentRequest;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AppointmentLifecycleMigrationTest extends TestCase
{
    public function test_additive_migration_preserves_existing_records_without_classification(): void
    {
        $this->assertSame('dtc_testing', DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->artisan('migrate:fresh')->assertExitCode(0);
        try {
            $migration = require database_path('migrations/2026_11_24_000001_add_appointment_lifecycle_integrity.php');
            $migration->down();
            $appointment = Appointment::factory()->create(['status' => 'cancelled']);
            $document = DocumentRequest::create(['user_id' => $appointment->user_id, 'appointment_id' => $appointment->id,
                'document_type' => 'tor', 'status' => 'released', 'fee' => 50, 'is_paid' => true,
                'payment_status' => 'verified', 'amount_received' => 50, 'receipt_no' => 'HISTORICAL-UNCHANGED']);
            $item = $appointment->documentItems()->create(['document_request_id' => $document->id,
                'released_at' => now(), 'released_by' => $appointment->user_id]);
            $beforeAppointment = (array) DB::table('appointments')->find($appointment->id);
            $beforeDocument = (array) DB::table('document_requests')->find($document->id);
            $beforeItem = (array) DB::table('appointment_document_items')->find($item->id);
            $migration->up();
            $afterAppointment = (array) DB::table('appointments')->find($appointment->id);
            $afterItem = (array) DB::table('appointment_document_items')->find($item->id);
            $this->assertSame($beforeAppointment, array_intersect_key($afterAppointment, $beforeAppointment));
            $this->assertSame($beforeDocument, (array) DB::table('document_requests')->find($document->id));
            $this->assertSame($beforeItem, array_intersect_key($afterItem, $beforeItem));
            $this->assertSame(0, $afterAppointment['lifecycle_version']);
            $this->assertNull($afterAppointment['previous_appointment_id']);
            foreach (['resolution', 'resolved_at', 'resolved_by'] as $field) {
                $this->assertNull($afterItem[$field]);
            }
            $this->assertDatabaseCount('appointment_lifecycle_events', 0);
        } finally {
            $this->artisan('migrate:fresh')->assertExitCode(0);
            RefreshDatabaseState::$migrated = false;
        }
    }
}
