<?php

namespace Tests\Feature;

use App\Http\Controllers\Registrar\AppointmentController;
use App\Models\Appointment;
use App\Models\AppointmentSlot;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\SeedsRoles;
use Tests\TestCase;

class AppointmentSlotConcurrencyTest extends TestCase
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

    public function test_booking_waits_for_capacity_update_and_slot_deletion(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $student = $this->makeUserWithRole('student');
        $slot = AppointmentSlot::factory()->create([
            'date' => now()->addDays(2)->toDateString(), 'status' => 'open', 'max_bookings' => 2,
        ]);
        Appointment::factory()->create(['appointment_slot_id' => $slot->id, 'status' => 'pending']);

        $result = $this->holdBooking($student->id, $slot->id, function () use ($registrar, $slot) {
            Auth::login($registrar);
            app(AppointmentController::class)->updateSlot(Request::create('/', 'PUT', [
                'date' => $slot->date,
                'start_time' => substr($slot->start_time, 0, 5),
                'end_time' => substr($slot->end_time, 0, 5),
                'max_bookings' => 1,
            ]), $slot);
        });
        $this->assertStringContainsString('ValidationException', $result);
        $this->assertSame(1, Appointment::where('appointment_slot_id', $slot->id)->count());
        $this->assertSame(1, $slot->fresh()->max_bookings);

        $deletable = AppointmentSlot::factory()->create([
            'date' => now()->addDays(3)->toDateString(), 'status' => 'open', 'max_bookings' => 1,
        ]);
        $result = $this->holdBooking($student->id, $deletable->id, function () use ($registrar, $deletable) {
            Auth::login($registrar);
            app(AppointmentController::class)->deleteSlot($deletable);
        });
        $this->assertStringContainsString('ModelNotFoundException', $result);
        $this->assertDatabaseMissing('appointment_slots', ['id' => $deletable->id]);
        $this->assertDatabaseMissing('appointments', ['appointment_slot_id' => $deletable->id, 'user_id' => $student->id]);
    }

    private function holdBooking(int $userId, int $slotId, callable $staffChange): string
    {
        $directory = storage_path('framework/testing/document-slot-concurrency-'.bin2hex(random_bytes(6)));
        mkdir($directory, 0777, true);
        $pipes = [];
        $process = null;
        try {
            DB::beginTransaction();
            AppointmentSlot::whereKey($slotId)->lockForUpdate()->firstOrFail();
            $process = proc_open([
                PHP_BINARY, base_path('tests/Support/document-appointment-booking-worker.php'),
                (string) $userId, (string) $slotId, $directory.'/ready', $directory.'/result',
            ], [0 => ['pipe', 'r'], 1 => ['file', $directory.'/out', 'w'], 2 => ['file', $directory.'/err', 'w']], $pipes, base_path());
            $this->assertIsResource($process);
            fclose($pipes[0]);

            $waiting = false;
            $deadline = microtime(true) + 20;
            do {
                if (is_file($directory.'/ready')) {
                    $connectionId = (int) file_get_contents($directory.'/ready');
                    $waiting = (int) DB::selectOne("SELECT COUNT(*) AS total FROM information_schema.innodb_trx WHERE trx_mysql_thread_id = ? AND trx_state = 'LOCK WAIT'", [$connectionId])->total === 1;
                }
                if (! $waiting) usleep(100000);
            } while (! $waiting && microtime(true) < $deadline);
            $this->assertTrue($waiting, 'The booking must wait on the appointment-slot lock.');

            $staffChange();
            DB::commit();
            proc_close($process);
            return (string) file_get_contents($directory.'/result');
        } finally {
            if (DB::transactionLevel() > 0) DB::rollBack();
            if (is_resource($process)) proc_terminate($process);
            foreach (glob($directory.'/*') ?: [] as $file) unlink($file);
            if (is_dir($directory)) rmdir($directory);
        }
    }

    public function test_student_and_alumni_competing_for_last_submission_slot_cannot_overbook(): void
    {
        $users = [$this->makeUserWithRole('student'), $this->makeUserWithRole('alumni')];
        $slot = AppointmentSlot::factory()->create(['date' => now()->addDays(2)->toDateString(),
            'status' => 'open', 'max_bookings' => 1]);
        $directory = storage_path('framework/testing/submission-concurrency-'.bin2hex(random_bytes(6)));
        mkdir($directory, 0777, true);
        $processes = [];
        try {
            DB::beginTransaction();
            AppointmentSlot::whereKey($slot->id)->lockForUpdate()->firstOrFail();
            foreach ($users as $index => $user) {
                $processes[$index] = proc_open([
                    PHP_BINARY, base_path('tests/Support/document-appointment-booking-worker.php'),
                    (string) $user->id, (string) $slot->id, $directory.'/'.$index.'.ready', $directory.'/'.$index.'.result',
                ], [0 => ['pipe', 'r'], 1 => ['file', $directory.'/'.$index.'.out', 'w'], 2 => ['file', $directory.'/'.$index.'.err', 'w']], $pipes, base_path());
                $this->assertIsResource($processes[$index]);
                fclose($pipes[0]);
            }
            $waiting = false;
            $deadline = microtime(true) + 20;
            do {
                if (is_file($directory.'/0.ready') && is_file($directory.'/1.ready')) {
                    $ids = [(int) file_get_contents($directory.'/0.ready'), (int) file_get_contents($directory.'/1.ready')];
                    $waiting = (int) DB::selectOne("SELECT COUNT(*) AS total FROM information_schema.innodb_trx WHERE trx_mysql_thread_id IN (?, ?) AND trx_state = 'LOCK WAIT'", $ids)->total === 2;
                }
                if (! $waiting) usleep(100000);
            } while (! $waiting && microtime(true) < $deadline);
            $this->assertTrue($waiting, 'Both submission workers must wait on the same slot lock.');
            DB::commit();
            foreach ($processes as $process) {
                $deadline = microtime(true) + 20;
                while (proc_get_status($process)['running'] && microtime(true) < $deadline) usleep(100000);
                $this->assertFalse(proc_get_status($process)['running']);
            }
            $results = [file_get_contents($directory.'/0.result'), file_get_contents($directory.'/1.result')];
            $this->assertSame(1, count(array_filter($results, fn ($result) => $result === 'booked')), json_encode($results));
            $this->assertStringContainsString('ValidationException', implode(' ', $results));
            $this->assertDatabaseCount('appointments', 1);
            $this->assertDatabaseCount('document_requests', 1);
            $this->assertDatabaseCount('appointment_document_items', 1);
            $this->assertSame('submission', Appointment::sole()->visit_type);
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
