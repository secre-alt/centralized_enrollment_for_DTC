<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentSlot;
use App\Models\DocumentRequest;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\SeedsRoles;
use Tests\TestCase;

class ClaimingAppointmentConcurrencyTest extends TestCase
{
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('dtc_testing', DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->artisan('migrate:fresh')->assertExitCode(0);
        $this->beforeApplicationDestroyed(function () {
            $this->artisan('migrate:fresh')->assertExitCode(0);
            RefreshDatabaseState::$migrated = false;
        });
        $this->seedRoles();
    }

    public function test_two_slots_cannot_reserve_the_same_document(): void
    {
        $owner = $this->makeUserWithRole('student');
        $document = DocumentRequest::create(['user_id' => $owner->id, 'document_type' => 'tor', 'fee' => 0, 'status' => 'ready']);
        $jobs = [];
        foreach ([1, 2] as $number) {
            $jobs[] = ['user' => $owner->id, 'slot' => AppointmentSlot::factory()->create()->id, 'documents' => [$document->id]];
        }
        $results = $this->race($jobs, fn () => DocumentRequest::whereKey($document->id)->lockForUpdate()->firstOrFail());
        $this->assertSame(1, count(array_filter($results, fn ($result) => $result === 'booked')), json_encode($results));
        $this->assertStringContainsString('ValidationException', implode(' ', $results));
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('appointment_document_items', 1);
        $this->assertSame(Appointment::sole()->id, $document->fresh()->pending_claim_appointment_id);
    }

    public function test_claim_and_submission_share_last_slot_capacity(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        $student = $this->makeUserWithRole('student');
        $document = DocumentRequest::create(['user_id' => $owner->id, 'document_type' => 'tor', 'fee' => 0, 'status' => 'ready']);
        $slot = AppointmentSlot::factory()->create(['max_bookings' => 1]);
        $results = $this->race([
            ['user' => $owner->id, 'slot' => $slot->id, 'documents' => [$document->id], 'snapshot' => true],
            ['user' => $student->id, 'slot' => $slot->id, 'action' => 'submission', 'snapshot' => true],
        ], fn () => AppointmentSlot::whereKey($slot->id)->lockForUpdate()->firstOrFail());
        $this->assertSame(1, count(array_filter($results, fn ($result) => $result === 'booked')), json_encode($results));
        $this->assertStringContainsString('ValidationException', implode(' ', $results));
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_claim_waiting_for_cashier_verification_reads_committed_payment_state(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        $cashier = $this->makeUserWithRole('cashier');
        $document = DocumentRequest::create(['user_id' => $owner->id, 'document_type' => 'tor',
            'fee' => 50, 'status' => 'ready', 'is_paid' => false, 'payment_status' => 'unpaid', 'payment_method' => 'walk_in']);
        $slot = AppointmentSlot::factory()->create();
        $results = $this->race([['user' => $owner->id, 'slot' => $slot->id, 'documents' => [$document->id], 'snapshot' => true]],
            fn () => DocumentRequest::whereKey($document->id)->lockForUpdate()->firstOrFail(),
            function () use ($cashier, $document) {
                Auth::login($cashier);
                $request = Request::create('/', 'POST', ['amount_received' => 50, 'receipt_no' => 'CLAIM-RACE-OR']);
                $request->setUserResolver(fn () => $cashier);
                app(\App\Http\Controllers\Cashier\DocumentPaymentController::class)->verifyWalkIn($request, $document);
            });
        $this->assertSame(['booked'], $results);
        $this->assertTrue($document->fresh()->paymentSatisfied());
        $this->assertSame('CLAIM-RACE-OR', $document->fresh()->receipt_no);
    }

    public function test_two_registrars_cannot_release_the_same_document_twice(): void
    {
        $owner = $this->makeUserWithRole('student');
        $claim = Appointment::factory()->create(['user_id' => $owner->id, 'visit_type' => 'claiming']);
        $document = DocumentRequest::create(['user_id' => $owner->id, 'document_type' => 'tor', 'fee' => 0, 'status' => 'ready']);
        $document->pending_claim_appointment_id = $claim->id;
        $document->save();
        $claim->documentItems()->create(['document_request_id' => $document->id]);
        $jobs = [];
        foreach ([1, 2] as $number) {
            $jobs[] = ['action' => 'release', 'user' => $this->makeUserWithRole('registrar')->id,
                'appointment' => $claim->id, 'documents' => [$document->id], 'snapshot' => true];
        }
        $results = $this->race($jobs, fn () => AppointmentSlot::whereKey($claim->appointment_slot_id)->lockForUpdate()->firstOrFail());
        $this->assertSame(1, count(array_filter($results, fn ($result) => $result === 'released')), json_encode($results));
        $this->assertStringContainsString('ValidationException', implode(' ', $results));
        $this->assertSame('released', $document->fresh()->status);
        $this->assertNull($document->fresh()->pending_claim_appointment_id);
        $this->assertSame('completed', $claim->fresh()->status);
        $this->assertSame(1, \App\Models\AuditLog::where('action', 'document_request.claim_released')->count());
        $item = $claim->documentItems()->sole();
        $this->assertNotNull($item->released_at);
        $this->assertContains($item->released_by, array_column($jobs, 'user'));
    }

    public function test_concurrent_partial_handovers_preserve_both_releases_and_complete_once(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        $claim = Appointment::factory()->create(['user_id' => $owner->id, 'visit_type' => 'claiming']);
        $jobs = [];
        foreach ([1, 2] as $number) {
            $document = DocumentRequest::create(['user_id' => $owner->id, 'document_type' => 'tor', 'fee' => 0, 'status' => 'ready']);
            $document->pending_claim_appointment_id = $claim->id;
            $document->save();
            $claim->documentItems()->create(['document_request_id' => $document->id]);
            $jobs[] = ['action' => 'release', 'user' => $this->makeUserWithRole('registrar')->id,
                'appointment' => $claim->id, 'documents' => [$document->id], 'snapshot' => true];
        }
        $results = $this->race($jobs,
            fn () => AppointmentSlot::whereKey($claim->appointment_slot_id)->lockForUpdate()->firstOrFail());
        $this->assertSame(1, count(array_filter($results, fn ($result) => $result === 'released')), json_encode($results));
        $this->assertStringContainsString('ValidationException', implode(' ', $results));
        $remaining = $claim->documentItems()->whereNull('released_at')->sole();
        $registrar = $this->makeUserWithRole('registrar');
        Auth::login($registrar);
        $request = Request::create('/', 'POST', ['operation_key' => (string) \Illuminate\Support\Str::uuid(), 'handover_confirmed' => 1, 'lifecycle_version' => $claim->fresh()->lifecycle_version]);
        $request->setUserResolver(fn () => $registrar);
        app(\App\Services\ClaimingAppointmentReleaseService::class)->release($registrar, $claim->id, [$remaining->document_request_id], $request);
        $this->assertSame(2, $claim->documentItems()->whereNotNull('released_at')->count());
        $this->assertSame('completed', $claim->fresh()->status);
        $this->assertSame(2, \App\Models\AuditLog::where('action', 'document_request.claim_released')->count());
        $this->assertSame(1, \App\Models\AuditLog::where('action', 'appointment.claiming_completed')->count());
    }

    public function test_release_rechecks_payment_after_waiting_on_a_document_lock(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        $cashier = $this->makeUserWithRole('cashier');
        $claim = Appointment::factory()->create(['user_id' => $owner->id, 'visit_type' => 'claiming']);
        $document = DocumentRequest::create(['user_id' => $owner->id, 'document_type' => 'tor',
            'fee' => 50, 'status' => 'ready', 'is_paid' => false, 'payment_status' => 'unpaid', 'payment_method' => 'walk_in']);
        $document->pending_claim_appointment_id = $claim->id;
        $document->save();
        $claim->documentItems()->create(['document_request_id' => $document->id]);
        $results = $this->race([['action' => 'release', 'user' => $this->makeUserWithRole('registrar')->id,
            'appointment' => $claim->id, 'documents' => [$document->id], 'snapshot' => true]],
            fn () => DocumentRequest::whereKey($document->id)->lockForUpdate()->firstOrFail(),
            function () use ($cashier, $document) {
                Auth::login($cashier);
                $request = Request::create('/', 'POST', ['amount_received' => 50, 'receipt_no' => 'RELEASE-RACE-OR']);
                $request->setUserResolver(fn () => $cashier);
                app(\App\Http\Controllers\Cashier\DocumentPaymentController::class)->verifyWalkIn($request, $document);
            });
        $this->assertSame(['released'], $results);
        $this->assertSame('released', $document->fresh()->status);
        $this->assertTrue($document->fresh()->paymentSatisfied());
        $this->assertSame('RELEASE-RACE-OR', $document->fresh()->receipt_no);
    }

    public function test_competing_confirmations_have_one_versioned_outcome(): void
    {
        $owner = $this->makeUserWithRole('student');
        $appointment = Appointment::factory()->create(['user_id' => $owner->id, 'visit_type' => 'submission']);
        DocumentRequest::create(['user_id' => $owner->id, 'appointment_id' => $appointment->id, 'document_type' => 'tor', 'fee' => 0, 'status' => 'submitted']);
        $jobs = [];
        foreach ([1, 2] as $number) $jobs[] = ['action' => 'confirm', 'user' => $this->makeUserWithRole('registrar')->id,
            'appointment' => $appointment->id, 'lifecycle_version' => 0];
        $results = $this->race($jobs, fn () => AppointmentSlot::whereKey($appointment->appointment_slot_id)->lockForUpdate()->firstOrFail());
        $this->assertSame(1, count(array_filter($results, fn ($result) => $result === 'confirmed')), json_encode($results));
        $this->assertStringContainsString('ValidationException', implode(' ', $results));
        $this->assertSame(1, $appointment->fresh()->lifecycle_version);
        $this->assertDatabaseCount('appointment_lifecycle_events', 1);
        $this->assertDatabaseCount('document_requests', 1);
    }

    public function test_walk_in_registrars_cannot_release_legacy_alumni_document_twice(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        $document = DocumentRequest::create(['user_id' => $owner->id, 'document_type' => 'tor', 'fee' => 0, 'status' => 'ready']);
        $jobs = [];
        foreach ([1, 2] as $number) $jobs[] = ['action' => 'walkin', 'user' => $this->makeUserWithRole('registrar')->id,
            'claimant' => $owner->id, 'documents' => [$document->id]];
        $results = $this->race($jobs, fn () => DocumentRequest::whereKey($document->id)->lockForUpdate()->firstOrFail());
        $this->assertSame(1, count(array_filter($results, fn ($result) => $result === 'released')), json_encode($results));
        $this->assertStringContainsString('ValidationException', implode(' ', $results));
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('document_workflow_events', 1);
        $this->assertNotNull($document->fresh()->released_at);
        $this->assertSame(1, \App\Models\UserNotification::where('user_id', $owner->id)->where('event_key', 'document-released:'.$document->id)->count());
    }

    public function test_walk_in_and_historical_handover_cannot_both_release(): void
    {
        $owner = $this->makeUserWithRole('student');
        $claim = Appointment::factory()->create(['user_id' => $owner->id, 'visit_type' => 'claiming']);
        $document = DocumentRequest::create(['user_id' => $owner->id, 'document_type' => 'tor', 'fee' => 0, 'status' => 'ready']);
        $document->pending_claim_appointment_id = $claim->id; $document->save();
        $claim->documentItems()->create(['document_request_id' => $document->id]);
        $results = $this->race([
            ['action' => 'walkin', 'user' => $this->makeUserWithRole('registrar')->id, 'claimant' => $owner->id, 'documents' => [$document->id]],
            ['action' => 'release', 'user' => $this->makeUserWithRole('registrar')->id, 'appointment' => $claim->id, 'lifecycle_version' => 0, 'documents' => [$document->id]],
        ], fn () => AppointmentSlot::whereKey($claim->appointment_slot_id)->lockForUpdate()->firstOrFail());
        $this->assertSame(1, count(array_filter($results, fn ($result) => $result === 'released')), json_encode($results));
        $this->assertStringContainsString('ValidationException', implode(' ', $results));
        $this->assertDatabaseCount('document_workflow_events', 1);
        $this->assertSame(1, $claim->fresh()->lifecycle_version);
        $this->assertSame('completed', $claim->fresh()->status);
        $this->assertNotNull($document->fresh()->released_at);
    }

    public function test_identical_walk_in_commands_return_one_recorded_release(): void
    {
        $owner = $this->makeUserWithRole('student');
        $document = DocumentRequest::create(['user_id' => $owner->id, 'document_type' => 'tor', 'fee' => 0, 'status' => 'ready']);
        $job = ['action' => 'walkin', 'user' => $this->makeUserWithRole('registrar')->id, 'claimant' => $owner->id,
            'documents' => [$document->id], 'operation_key' => (string) \Illuminate\Support\Str::uuid()];
        $this->assertSame(['released', 'released'], $this->race([$job, $job], fn () => DocumentRequest::whereKey($document->id)->lockForUpdate()->firstOrFail()));
        $this->assertDatabaseCount('document_workflow_events', 1);
        $this->assertSame(1, \App\Models\UserNotification::where('user_id', $owner->id)->count());
    }

    public function test_edit_competing_with_confirmation_rejects_the_stale_command(): void
    {
        $owner = $this->makeUserWithRole('student');
        $appointment = Appointment::factory()->create(['user_id' => $owner->id, 'visit_type' => 'submission', 'document_type' => 'transcript']);
        DocumentRequest::create(['user_id' => $owner->id, 'appointment_id' => $appointment->id, 'document_type' => 'tor', 'fee' => 0, 'status' => 'submitted']);
        $results = $this->race([
            ['action' => 'edit', 'user' => $owner->id, 'appointment' => $appointment->id, 'lifecycle_version' => 0, 'slot' => AppointmentSlot::factory()->create()->id],
            ['action' => 'confirm', 'user' => $this->makeUserWithRole('registrar')->id, 'appointment' => $appointment->id, 'lifecycle_version' => 0],
        ], fn () => AppointmentSlot::whereKey($appointment->appointment_slot_id)->lockForUpdate()->firstOrFail());
        $this->assertSame(1, count(array_filter($results, fn ($result) => in_array($result, ['updated', 'confirmed'], true))), json_encode($results));
        $this->assertStringContainsString('ValidationException', implode(' ', $results));
        $this->assertSame(1, $appointment->fresh()->lifecycle_version);
        $this->assertDatabaseCount('appointment_lifecycle_events', 1);
    }

    public function test_identical_concurrent_booking_commands_replay_one_recorded_outcome(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        $slot = AppointmentSlot::factory()->create();
        $document = DocumentRequest::create(['user_id' => $owner->id, 'document_type' => 'tor', 'fee' => 0, 'status' => 'ready']);
        $job = ['user' => $owner->id, 'slot' => $slot->id, 'documents' => [$document->id], 'operation_key' => (string) \Illuminate\Support\Str::uuid()];
        $this->assertSame(['booked', 'booked'], $this->race([$job, $job], fn () => AppointmentSlot::whereKey($slot->id)->lockForUpdate()->firstOrFail()));
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('appointment_lifecycle_events', 1);
        $this->assertSame(1, Appointment::sole()->lifecycle_version);
    }

    public function test_identical_concurrent_standalone_submissions_create_one_set(): void
    {
        $owner = $this->makeUserWithRole('student');
        $job = ['action' => 'standalone', 'user' => $owner->id, 'operation_key' => (string) \Illuminate\Support\Str::uuid()];
        $this->assertSame(['submitted', 'submitted'], $this->race([$job, $job], fn () => \App\Models\User::whereKey($owner->id)->lockForUpdate()->firstOrFail()));
        $this->assertDatabaseCount('document_requests', 1);
        $this->assertDatabaseCount('document_workflow_events', 1);
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('appointment_payments', 0);
    }

    public function test_identical_concurrent_ready_commands_create_one_event_and_notice(): void
    {
        $owner = $this->makeUserWithRole('student');
        $document = DocumentRequest::create(['user_id' => $owner->id, 'document_type' => 'tor', 'fee' => 0, 'status' => 'processing']);
        $job = ['action' => 'ready', 'user' => $this->makeUserWithRole('registrar')->id, 'documents' => [$document->id], 'operation_key' => (string) \Illuminate\Support\Str::uuid()];
        $this->assertSame(['ready', 'ready'], $this->race([$job, $job], fn () => DocumentRequest::whereKey($document->id)->lockForUpdate()->firstOrFail()));
        $this->assertDatabaseCount('document_workflow_events', 1);
        $this->assertSame(1, \App\Models\UserNotification::where('user_id', $owner->id)->count());
        $this->assertSame('ready', $document->fresh()->status);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_competing_ready_commands_allow_only_one_transition(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        $document = DocumentRequest::create(['user_id' => $owner->id, 'document_type' => 'tor', 'fee' => 0, 'status' => 'processing']);
        $job = ['action' => 'ready', 'user' => $this->makeUserWithRole('registrar')->id, 'documents' => [$document->id]];
        $results = $this->race([
            $job + ['operation_key' => (string) \Illuminate\Support\Str::uuid()],
            $job + ['operation_key' => (string) \Illuminate\Support\Str::uuid()],
        ], fn () => DocumentRequest::whereKey($document->id)->lockForUpdate()->firstOrFail());
        $this->assertSame(1, count(array_filter($results, fn ($result) => $result === 'ready')), json_encode($results));
        $this->assertStringContainsString('ValidationException', implode(' ', $results));
        $this->assertDatabaseCount('document_workflow_events', 1);
        $this->assertSame(1, \App\Models\UserNotification::where('user_id', $owner->id)->count());
    }

    private function race(array $jobs, callable $lock, ?callable $beforeCommit = null): array
    {
        $directory = storage_path('framework/testing/claiming-concurrency-'.bin2hex(random_bytes(6)));
        mkdir($directory, 0777, true);
        $processes = [];
        try {
            DB::beginTransaction();
            $lock();
            foreach ($jobs as $index => $job) {
                file_put_contents($directory.'/'.$index.'.job', json_encode($job));
                $processes[$index] = proc_open([PHP_BINARY, base_path('tests/Support/claiming-appointment-worker.php'),
                    $directory.'/'.$index.'.job', $directory.'/'.$index.'.ready', $directory.'/'.$index.'.result'],
                    [0 => ['pipe', 'r'], 1 => ['file', $directory.'/'.$index.'.out', 'w'], 2 => ['file', $directory.'/'.$index.'.err', 'w']], $pipes, base_path());
                $this->assertIsResource($processes[$index]);
                fclose($pipes[0]);
            }
            $deadline = microtime(true) + 20;
            $waiting = false;
            do {
                $ids = [];
                foreach (array_keys($jobs) as $index) {
                    if (is_file($directory.'/'.$index.'.ready')) $ids[] = (int) file_get_contents($directory.'/'.$index.'.ready');
                }
                if (count($ids) === count($jobs)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $waiting = (int) DB::selectOne("SELECT COUNT(*) AS total FROM information_schema.innodb_trx WHERE trx_mysql_thread_id IN ($placeholders) AND trx_state = 'LOCK WAIT'", $ids)->total === count($jobs);
                }
                if (! $waiting) usleep(100000);
            } while (! $waiting && microtime(true) < $deadline);
            $diagnostics = [];
            foreach (glob($directory.'/*') ?: [] as $file) $diagnostics[basename($file)] = file_get_contents($file);
            $this->assertTrue($waiting, 'All workers must be concurrently waiting on the locked slot or document. '.json_encode($diagnostics));
            if ($beforeCommit) $beforeCommit();
            DB::commit();
            $results = [];
            foreach ($processes as $index => $process) {
                $deadline = microtime(true) + 20;
                while (proc_get_status($process)['running'] && microtime(true) < $deadline) usleep(100000);
                $this->assertFalse(proc_get_status($process)['running'], 'Claiming worker timed out.');
                $results[] = file_get_contents($directory.'/'.$index.'.result');
            }
            return $results;
        } finally {
            if (DB::transactionLevel()) DB::rollBack();
            foreach ($processes as $process) {
                if (is_resource($process)) {
                    if (proc_get_status($process)['running']) proc_terminate($process);
                    proc_close($process);
                }
            }
            foreach (glob($directory.'/*') ?: [] as $file) unlink($file);
            rmdir($directory);
        }
    }
}
