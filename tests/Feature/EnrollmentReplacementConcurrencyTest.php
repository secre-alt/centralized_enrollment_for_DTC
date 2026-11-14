<?php

namespace Tests\Feature;

use App\Models\ClassSchedule;
use App\Models\CourseSubject;
use App\Models\Enrollment;
use App\Models\EnrollmentReplacementRequest;
use App\Models\EnrollmentSubjectOffering;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\SeedsRoles;
use Tests\TestCase;

class EnrollmentReplacementConcurrencyTest extends TestCase
{
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('dtc_testing', DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->artisan('migrate:fresh')->assertExitCode(0);
        // Workers need committed fixtures, so this test cannot use the usual
        // wrapping transaction. Recreate only the dedicated test database.
        $this->beforeApplicationDestroyed(function () {
            $this->artisan('migrate:fresh')->assertExitCode(0);
            RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_simultaneous_submissions_cannot_overbook_the_last_seat(): void
    {
        $this->assertSame('dtc_testing', DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->seedRoles();
        $registrar = $this->makeUserWithRole('registrar');
        $subject = CourseSubject::factory()->create(['classification' => 'minor']);
        $offering = ClassSchedule::create([
            'course_subject_id' => $subject->id, 'section_code' => 'LAST', 'school_year' => '2026-2027',
            'semester' => 1, 'capacity' => 1, 'is_active' => true, 'day_pattern' => 'MWF',
            'time_start' => '08:00:00', 'time_end' => '09:00:00', 'room' => 'TEST',
        ]);
        $originalOffering = $offering->replicate();
        $originalOffering->fill(['section_code' => 'ORIGINAL', 'capacity' => 10])->save();
        $cases = [];
        foreach ([1, 2] as $number) {
            $student = $this->makeUserWithRole('student');
            $enrollment = Enrollment::factory()->pending()->create([
                'user_id' => $student->id, 'program_id' => $subject->program_id,
                'school_year' => '2026-2027', 'semester' => 1, 'subject_ids' => [$subject->id], 'is_paid' => true,
            ]);
            Payment::factory()->create(['enrollment_id' => $enrollment->id, 'status' => 'verified', 'refund_status' => 'not_required']);
            $case = EnrollmentReplacementRequest::create([
                'enrollment_id' => $enrollment->id, 'requested_by' => $registrar->id,
                'status' => 'open', 'reason' => 'Select a replacement section.', 'baseline_is_irregular' => false,
            ]);
            EnrollmentSubjectOffering::create([
                'enrollment_id' => $enrollment->id, 'curriculum_subject_id' => $subject->id,
                'class_schedule_id' => $originalOffering->id,
            ]);
            $item = $case->items()->create(['original_subject_id' => $subject->id, 'original_schedule_id' => $originalOffering->id]);
            $cases[] = [$enrollment, $item];
        }
        $directory = storage_path('framework/testing/replacement-concurrency-'.bin2hex(random_bytes(6)));
        mkdir($directory, 0777, true);
        $processes = [];
        $pipes = [];
        try {
            DB::beginTransaction();
            ClassSchedule::whereKey($offering->id)->lockForUpdate()->firstOrFail();
            foreach ($cases as $index => [$enrollment, $item]) {
                $processes[$index] = proc_open([
                    PHP_BINARY, base_path('tests/Support/enrollment-replacement-worker.php'),
                    (string) $enrollment->id, (string) $item->id, (string) $offering->id,
                    $directory.'/'.$index.'.ready', $directory.'/'.$index.'.result',
                ], [0 => ['pipe', 'r'], 1 => ['file', $directory.'/'.$index.'.out', 'w'], 2 => ['file', $directory.'/'.$index.'.err', 'w']], $pipes[$index], base_path());
                $this->assertIsResource($processes[$index]);
                fclose($pipes[$index][0]);
            }
            $waiting = false;
            $deadline = microtime(true) + 15;
            do {
                if (is_file($directory.'/0.ready') && is_file($directory.'/1.ready')) {
                    $ids = [(int) file_get_contents($directory.'/0.ready'), (int) file_get_contents($directory.'/1.ready')];
                    $waiting = (int) DB::selectOne("SELECT COUNT(*) AS total FROM information_schema.innodb_trx WHERE trx_mysql_thread_id IN (?, ?) AND trx_state = 'LOCK WAIT'", $ids)->total === 2;
                }
                if (! $waiting) {
                    usleep(100000);
                }
            } while (! $waiting && microtime(true) < $deadline);
            $this->assertTrue($waiting, 'Both workers must be waiting concurrently on the offering row lock.');
            DB::commit();
            foreach ($processes as $process) {
                $deadline = microtime(true) + 20;
                while (proc_get_status($process)['running'] && microtime(true) < $deadline) {
                    usleep(100000);
                }
                $this->assertFalse(proc_get_status($process)['running'], 'Worker did not complete within 20 seconds.');
            }
            $results = [file_get_contents($directory.'/0.result'), file_get_contents($directory.'/1.result')];
            $this->assertSame(1, count(array_filter($results, fn ($result) => $result === 'submitted')), json_encode($results));
            $rejected = array_values(array_filter($results, fn ($result) => $result !== 'submitted'));
            $this->assertArrayHasKey('replacements', json_decode($rejected[0], true)['errors'] ?? [], json_encode($results));
            $this->assertSame(1, EnrollmentSubjectOffering::where('class_schedule_id', $offering->id)->count());
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
