<?php

namespace Tests\Feature;

use App\Models\ClassSchedule;
use App\Models\CourseSubject;
use App\Models\Enrollment;
use App\Models\EnrollmentSubjectOffering;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\SeedsRoles;
use Tests\TestCase;

class EnrollmentTransitionConcurrencyTest extends TestCase
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

    private function fixture(bool $paid = true): array
    {
        $this->seedRoles();
        $subject = CourseSubject::factory()->create(['year_level' => 1, 'semester' => 1]);
        $enrollment = Enrollment::factory()->pending()->create([
            'user_id' => $this->makeUserWithRole('student')->id, 'program_id' => $subject->program_id,
            'subject_ids' => [$subject->id], 'school_year' => '2026-2027', 'semester' => 1,
            'is_paid' => $paid, 'requires_section_assignment' => ! $paid,
        ]);
        $section = ClassSchedule::create(['course_subject_id' => $subject->id, 'section_code' => 'LAST',
            'school_year' => '2026-2027', 'semester' => 1, 'capacity' => 1, 'is_active' => true,
            'day_pattern' => 'MWF', 'time_start' => '08:00', 'time_end' => '09:00']);
        $payment = $paid ? Payment::factory()->create(['enrollment_id' => $enrollment->id]) : null;

        return [$enrollment, $subject, $section, $payment];
    }

    private function job(string $action, $actor, $enrollment, array $payload = []): array
    {
        return ['action' => $action, 'actor' => $actor->id, 'enrollment' => $enrollment->id, 'payload' => $payload];
    }

    public function test_manual_section_assignments_compete_for_one_seat(): void
    {
        [$first, $subject, $section] = $this->fixture(false);
        $second = Enrollment::factory()->pending()->create(array_merge($first->only([
            'program_id', 'subject_ids', 'school_year', 'semester', 'year_level',
        ]), ['user_id' => $this->makeUserWithRole('student')->id, 'is_paid' => false, 'requires_section_assignment' => true]));
        $registrar = $this->makeUserWithRole('registrar');
        $payload = ['record_sections' => '1', 'section_choices' => [$subject->id => $section->id]];
        $this->race([
            $this->job('approve', $registrar, $first, $payload), $this->job('approve', $registrar, $second, $payload),
        ], fn () => CourseSubject::whereKey($subject->id)->lockForUpdate()->firstOrFail());
        $this->assertSame(1, EnrollmentSubjectOffering::where('class_schedule_id', $section->id)->count());
        $this->assertSame(1, Enrollment::where('requires_section_assignment', true)->count());
        $this->assertSame(2, Enrollment::where('status', 'pending')->where('is_paid', false)->count());
        $this->assertSame(0, Payment::count());
    }

    public function test_duplicate_approval_commits_once_and_preserves_payment(): void
    {
        [$enrollment, $subject, $section, $payment] = $this->fixture();
        $before = $payment->fresh()->getRawOriginal();
        $registrar = $this->makeUserWithRole('registrar');
        $job = $this->job('approve', $registrar, $enrollment, ['section_choices' => [$subject->id => $section->id]]);
        $this->race([$job, $job], fn () => Enrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail());
        $this->assertSame('approved', $enrollment->fresh()->status);
        $this->assertSame(1, $enrollment->subjectOfferings()->count());
        $this->assertSame(1, DB::table('student_profiles')->where('user_id', $enrollment->user_id)->count());
        $this->assertSame($before, $payment->fresh()->getRawOriginal());
    }

    public function test_recovery_and_refund_confirmation_cannot_both_commit(): void
    {
        [$enrollment, $subject, $section, $payment] = $this->fixture();
        $enrollment->update(['status' => 'rejected', 'remarks' => 'Full subject needs correction.']);
        $payment->update(['refund_status' => 'pending']);
        $before = $payment->fresh()->getRawOriginal();
        $cashier = $this->makeUserWithRole('cashier');
        $this->race([
            $this->job('recover', $this->makeUserWithRole('registrar'), $enrollment, [
                'affected_subject_ids' => [$subject->id], 'reason' => 'Correct rejection for unavailable subject.',
                'recover_rejection' => '1', 'confirm_no_refund' => '1']),
            $this->job('refund', $cashier, $enrollment, ['refund_amount' => '500.00', 'refund_reference' => 'RACE-FULL-REFUND']),
        ], fn () => User::whereKey($enrollment->user_id)->lockForUpdate()->firstOrFail());
        $payment->refresh();
        foreach (['status', 'amount', 'receipt_no', 'paid_at', 'verified_by', 'processed_by', 'enrollment_id'] as $field) {
            $this->assertSame($before[$field], $payment->getRawOriginal($field));
        }
        if ($enrollment->fresh()->status === 'pending') {
            $this->assertSame('not_required', $payment->refund_status);
            $this->assertNull($payment->refund_reference);
            $this->assertSame(1, $enrollment->replacementRequests()->where('status', 'open')->count());
        } else {
            $this->assertSame('rejected', $enrollment->fresh()->status);
            $this->assertSame('refunded', $payment->refund_status);
            $this->assertSame('RACE-FULL-REFUND', $payment->refund_reference);
            $this->assertSame($cashier->id, $payment->refunded_by);
            $this->assertNotNull($payment->refunded_at);
            $this->assertSame(0, $enrollment->replacementRequests()->count());
        }
        $this->assertSame(1, Payment::count());
    }

    public function test_recovery_and_new_submission_leave_only_one_active_enrollment(): void
    {
        [$enrollment, $subject, $section, $payment] = $this->fixture();
        Setting::set('current_school_year', '2026-2027');
        Setting::set('current_semester', '1st');
        $enrollment->update(['status' => 'rejected']);
        $payment->update(['refund_status' => 'pending']);
        $this->race([
            $this->job('recover', $this->makeUserWithRole('registrar'), $enrollment, [
                'affected_subject_ids' => [$subject->id], 'reason' => 'Correct rejection for unavailable subject.',
                'recover_rejection' => '1', 'confirm_no_refund' => '1']),
            $this->job('create', $enrollment->user, $enrollment, ['program_id' => $subject->program_id,
                'year_level' => 1, 'semester' => 1, 'subject_ids' => [$subject->id]]),
        ], fn () => User::whereKey($enrollment->user_id)->lockForUpdate()->firstOrFail());
        $this->assertSame(1, Enrollment::where('user_id', $enrollment->user_id)->where('school_year', '2026-2027')
            ->where('semester', 1)->whereIn('status', ['pending', 'approved'])->count());
        $this->assertSame(1, Payment::count());
        $this->assertSame('verified', $payment->fresh()->status);
        $this->assertSame($enrollment->fresh()->status === 'pending' ? 'not_required' : 'pending', $payment->fresh()->refund_status);
        $this->assertSame($enrollment->fresh()->status === 'pending' ? 1 : 0, $enrollment->replacementRequests()->count());
    }

    public function test_duplicate_refund_confirmation_records_only_one_confirmation(): void
    {
        [$enrollment, $subject, $section, $payment] = $this->fixture();
        $enrollment->update(['status' => 'rejected']);
        $payment->update(['refund_status' => 'pending']);
        $before = $payment->fresh()->getRawOriginal();
        $cashier = $this->makeUserWithRole('cashier');
        $jobs = [];
        foreach (['REFUND-A', 'REFUND-B'] as $reference) {
            $jobs[] = $this->job('refund', $cashier, $enrollment, ['refund_amount' => '500.00', 'refund_reference' => $reference]);
        }
        $results = $this->race($jobs, fn () => User::whereKey($enrollment->user_id)->lockForUpdate()->firstOrFail());
        $winner = $results[0]['outcome'] === 'committed' ? 0 : 1;
        $payment->refresh();
        $this->assertSame($jobs[$winner]['payload']['refund_reference'], $payment->refund_reference);
        $this->assertSame('500.00', $payment->refund_amount);
        $this->assertSame('refunded', $payment->refund_status);
        $this->assertSame('rejected', $enrollment->fresh()->status);
        $this->assertSame($before['paid_at'], $payment->getRawOriginal('paid_at'));
        $this->assertSame($before['receipt_no'], $payment->receipt_no);
        $this->assertSame('verified', $payment->status);
        $this->assertSame(1, Payment::count());
    }

    public function test_duplicate_recovery_creates_only_one_revision_and_audit_record(): void
    {
        [$enrollment, $subject, $section, $payment] = $this->fixture();
        $enrollment->update(['status' => 'rejected', 'remarks' => 'Original full-section rejection.']);
        $payment->update(['refund_status' => 'pending']);
        $before = $payment->fresh()->getRawOriginal();
        $job = $this->job('recover', $this->makeUserWithRole('registrar'), $enrollment, [
            'affected_subject_ids' => [$subject->id], 'reason' => 'Correct rejection for unavailable subject.',
            'recover_rejection' => '1', 'confirm_no_refund' => '1',
        ]);
        $this->race([$job, $job], fn () => User::whereKey($enrollment->user_id)->lockForUpdate()->firstOrFail());
        $this->assertSame('pending', $enrollment->fresh()->status);
        $this->assertSame(1, $enrollment->replacementRequests()->where('status', 'open')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'enrollment.rejection_recovered')->count());
        $this->assertSame('not_required', $payment->fresh()->refund_status);
        foreach (['status', 'amount', 'receipt_no', 'paid_at', 'verified_by', 'processed_by', 'enrollment_id'] as $field) {
            $this->assertSame($before[$field], $payment->fresh()->getRawOriginal($field));
        }
        $this->assertSame(1, Payment::count());
    }

    private function race(array $jobs, callable $lock): array
    {
        $directory = storage_path('framework/testing/transitions-'.bin2hex(random_bytes(6)));
        mkdir($directory, 0777, true);
        $processes = [];
        $pipes = [];
        try {
            DB::beginTransaction();
            $lock();
            foreach ($jobs as $index => $job) {
                file_put_contents($directory.'/'.$index.'.job', json_encode($job));
                $processes[$index] = proc_open([PHP_BINARY, base_path('tests/Support/enrollment-transition-worker.php'),
                    $directory.'/'.$index.'.job', $directory.'/'.$index.'.ready', $directory.'/'.$index.'.result'],
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
            $results = [];
            foreach ($jobs as $index => $job) {
                $this->assertFileExists($directory.'/'.$index.'.result');
                $results[] = json_decode(file_get_contents($directory.'/'.$index.'.result'), true, 512, JSON_THROW_ON_ERROR);
            }
            $this->assertSame(1, count(array_filter($results, fn ($r) => $r['outcome'] === 'committed')), json_encode($results));
            $this->assertSame(1, count(array_filter($results, fn ($r) => $r['outcome'] === 'validation')), json_encode($results));

            return $results;
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
