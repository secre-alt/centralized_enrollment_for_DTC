<?php

namespace Tests\Feature;

use App\Models\ClassSchedule;
use App\Models\CourseSubject;
use App\Models\Enrollment;
use App\Models\EnrollmentSubjectOffering;
use App\Models\Setting;
use App\Models\TimetableDraft;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\SeedsRoles;
use Tests\TestCase;

class EnrollmentAutomaticSectionsConcurrencyTest extends TestCase
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
    }

    public function test_two_students_competing_for_last_group_seat_leave_one_on_prepayment_hold(): void
    {
        $this->assertLastSeat(false);
    }

    public function test_last_seat_with_historical_enrollments_and_older_snapshots(): void
    {
        $this->assertLastSeat(true);
    }

    private function assertLastSeat(bool $withHistory): void
    {
        $this->seedRoles();
        Setting::set('current_school_year', '2026-2027');
        Setting::set('current_semester', '1st');
        $subject = CourseSubject::factory()->create(['year_level' => 1, 'semester' => 1]);
        $students = [$this->makeUserWithRole('student'), $this->makeUserWithRole('student')];
        if ($withHistory) {
            foreach ($students as $student) {
                Enrollment::factory()->create(['user_id' => $student->id, 'program_id' => $subject->program_id,
                    'school_year' => '2025-2026', 'semester' => 1, 'status' => 'rejected', 'is_paid' => false]);
            }
        }
        $group = TimetableDraft::create(['program_id' => $subject->program_id, 'year_level' => 1, 'semester' => 1,
            'school_year' => '2026-2027', 'group_code' => 'TEST 1A', 'capacity' => 1, 'status' => 'published',
            'configuration' => [], 'created_by' => $this->makeUserWithRole('admin')->id]);
        $schedule = ClassSchedule::create(['timetable_draft_id' => $group->id, 'course_subject_id' => $subject->id,
            'hosting_program_id' => $subject->program_id, 'section_code' => 'TEST 1A', 'school_year' => '2026-2027',
            'semester' => 1, 'capacity' => 1, 'is_active' => true, 'requires_irregular' => false,
            'day_pattern' => 'MWF', 'time_start' => '08:00', 'time_end' => '09:00', 'room' => '1T']);
        $directory = storage_path('framework/testing/auto-sections-'.bin2hex(random_bytes(6)));
        mkdir($directory, 0777, true);
        $processes = [];
        $pipes = [];
        try {
            DB::beginTransaction();
            // Hold curriculum validation so both requests remain in flight.
            // With empty enrollment indexes the other waiter may be on the
            // creation gap lock; the real controller retains deadlock retries.
            CourseSubject::whereKey($subject->id)->lockForUpdate()->firstOrFail();
            foreach ($students as $index => $student) {
                $processes[$index] = proc_open([PHP_BINARY, base_path('tests/Support/enrollment-auto-sections-worker.php'),
                    (string) $student->id, (string) $subject->id, $directory.'/'.$index.'.ready', $directory.'/'.$index.'.result'],
                    [0 => ['pipe', 'r'], 1 => ['file', $directory.'/'.$index.'.out', 'w'], 2 => ['file', $directory.'/'.$index.'.err', 'w']], $pipes[$index], base_path());
                $this->assertIsResource($processes[$index]);
                fclose($pipes[$index][0]);
            }
            $waiting = false;
            $deadline = microtime(true) + 25;
            do {
                if (is_file($directory.'/0.ready') && is_file($directory.'/1.ready')) {
                    $ids = [(int) file_get_contents($directory.'/0.ready'), (int) file_get_contents($directory.'/1.ready')];
                    $waiting = (int) DB::selectOne("SELECT COUNT(*) AS total FROM information_schema.innodb_trx WHERE trx_mysql_thread_id IN (?, ?) AND trx_state = 'LOCK WAIT'", $ids)->total === 2;
                }
                if (! $waiting) {
                    usleep(100000);
                }
            } while (! $waiting && microtime(true) < $deadline);
            $diagnostics = [];
            foreach (glob($directory.'/*') as $file) {
                $diagnostics[basename($file)] = file_get_contents($file);
            }
            $this->assertTrue($waiting, 'Both enrollment workers must reach a controlled lock wait. '.json_encode($diagnostics));
            DB::commit();
            foreach ($processes as $process) {
                $deadline = microtime(true) + 20;
                while (proc_get_status($process)['running'] && microtime(true) < $deadline) {
                    usleep(100000);
                }
                $this->assertFalse(proc_get_status($process)['running'], 'Enrollment worker timed out.');
            }
            $results = [file_get_contents($directory.'/0.result'), file_get_contents($directory.'/1.result')];
            sort($results);
            $this->assertSame(1, EnrollmentSubjectOffering::where('class_schedule_id', $schedule->id)->count());
            $this->assertSame(['assigned', 'held'], $results);
            $this->assertSame(1, Enrollment::where('requires_section_assignment', true)->count());
            $this->assertSame(2, Enrollment::where('status', 'pending')->where('is_paid', false)->count());
            $this->assertSame(0, DB::table('payments')->count());
        } finally {
            if (DB::transactionLevel()) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                if (is_resource($process)) {
                    if (proc_get_status($process)['running']) {
                        proc_terminate($process);
                    }
                    proc_close($process);
                }
            }
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
