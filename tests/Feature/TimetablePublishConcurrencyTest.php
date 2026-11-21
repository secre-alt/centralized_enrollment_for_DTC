<?php

namespace Tests\Feature;

use App\Models\ClassSchedule;
use App\Models\CourseSubject;
use App\Models\TimetableDraft;
use App\Models\TimetableResource;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\SeedsRoles;
use Tests\TestCase;

class TimetablePublishConcurrencyTest extends TestCase
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

    public function test_two_publishers_wait_on_same_lock_and_only_one_can_take_shared_room(): void
    {
        $this->seedRoles();
        $admin = $this->makeUserWithRole('admin');
        $room = TimetableResource::create(['kind' => 'room', 'name' => '1T', 'configuration' => ['capacity' => 40, 'facility' => 'classroom', 'active' => true]]);
        $instructor = TimetableResource::create(['kind' => 'instructor', 'name' => 'Shared teacher', 'configuration' => ['active' => true, 'availability' => array_fill_keys(['MO', 'WE', 'FR'], ['start' => '08:00', 'end' => '17:00'])]]);
        $drafts = [];
        foreach ([1, 2] as $number) {
            $subject = CourseSubject::factory()->create(['year_level' => 1, 'semester' => 1]);
            $drafts[] = TimetableDraft::create([
                'program_id' => $subject->program_id, 'year_level' => 1, 'semester' => 1, 'school_year' => '2026-2027',
                'group_code' => 'GROUP '.$number, 'capacity' => 30, 'created_by' => $admin->id,
                'configuration' => ['closing_hour' => 17],
                'proposals' => [['subject_id' => $subject->id, 'room_id' => $room->id, 'instructor_id' => $instructor->id,
                    'pattern' => 'MWF', 'minutes' => 60, 'facility' => 'classroom', 'start' => '08:00', 'end' => '09:00']],
            ]);
        }
        $directory = storage_path('framework/testing/timetable-concurrency-'.bin2hex(random_bytes(6)));
        mkdir($directory, 0777, true);
        $processes = [];
        $pipes = [];
        try {
            DB::beginTransaction();
            DB::table('timetable_locks')->where('id', 1)->lockForUpdate()->first();
            foreach ($drafts as $index => $draft) {
                $processes[$index] = proc_open([
                    PHP_BINARY, base_path('tests/Support/timetable-publish-worker.php'), (string) $draft->id, (string) $admin->id,
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
            $this->assertTrue($waiting, 'Both publishers must wait concurrently on the scheduling lock.');
            DB::commit();
            foreach ($processes as $process) {
                $deadline = microtime(true) + 20;
                while (proc_get_status($process)['running'] && microtime(true) < $deadline) {
                    usleep(100000);
                }
                $this->assertFalse(proc_get_status($process)['running'], 'Publisher did not finish within 20 seconds.');
            }
            $results = [file_get_contents($directory.'/0.result'), file_get_contents($directory.'/1.result')];
            $this->assertSame(1, count(array_filter($results, fn ($result) => $result === 'published')), json_encode($results));
            $denied = array_values(array_filter($results, fn ($result) => $result !== 'published'));
            $this->assertArrayHasKey('timetable', json_decode($denied[0], true)['errors'] ?? [], json_encode($results));
            $this->assertSame(1, ClassSchedule::count());
            $this->assertSame(1, TimetableDraft::where('status', 'published')->count());
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
