<?php

namespace Tests\Browser;

use App\Models\ClassSchedule;
use App\Models\CourseSubject;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\TimetableDraft;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\SeedsRoles;
use Tests\TestCase;

class AdmissionEnrollmentBrowserTest extends TestCase
{
    use SeedsRoles;

    public static function journeys(): array
    {
        return ['admission' => ['admission'], 'hold' => ['hold'], 'gcash' => ['gcash'],
            'replacement' => ['replacement'], 'refund' => ['refund'], 'roles' => ['roles'], 'layout' => ['layout']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('journeys')]
    public function test_real_browser_admission_and_enrollment_journeys(string $journey): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertFalse(app()->configurationIsCached(), 'Clear cached configuration before browser testing.');
        $this->assertSame('dtc_testing', DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->artisan('migrate:fresh')->assertExitCode(0);
        $this->beforeApplicationDestroyed(function () {
            $this->artisan('migrate:fresh')->assertExitCode(0);
            RefreshDatabaseState::$migrated = false;
        });
        $this->seedRoles();
        Setting::set('current_school_year', '2026-2027');
        Setting::set('current_semester', '1st');
        Setting::set('enrollment_fee', 500);
        $password = bin2hex(random_bytes(12)).'Aa1!';
        $registrar = $this->makeUserWithRole('registrar', ['email' => 'browser-registrar@example.test', 'password' => Hash::make($password), 'status' => 'active']);
        $cashier = $this->makeUserWithRole('cashier', ['email' => 'browser-cashier@example.test', 'password' => Hash::make($password), 'status' => 'active']);
        $subject = CourseSubject::factory()->create(['subject_code' => 'BROW101', 'year_level' => 1, 'semester' => 1]);
        $draft = TimetableDraft::create(['program_id' => $subject->program_id, 'year_level' => 1, 'semester' => 1,
            'school_year' => '2026-2027', 'group_code' => 'BROWSER 1A', 'capacity' => 1, 'status' => 'published',
            'configuration' => [], 'created_by' => $registrar->id]);
        $section = ClassSchedule::create(['timetable_draft_id' => $draft->id, 'course_subject_id' => $subject->id,
            'hosting_program_id' => $subject->program_id, 'section_code' => 'BROWSER 1A', 'school_year' => '2026-2027',
            'semester' => 1, 'capacity' => 1, 'is_active' => true, 'requires_irregular' => false,
            'day_pattern' => 'MWF', 'time_start' => '08:00', 'time_end' => '09:00', 'room' => '1T']);
        $paymentBefore = null;
        if ($journey === 'roles') {
            foreach (['admin', 'alumni', 'new_applicant'] as $role) {
                $account = $this->makeUserWithRole($role, ['email' => 'browser-'.$role.'@example.test',
                    'password' => Hash::make($password), 'status' => 'active']);
                if ($role === 'new_applicant') {
                    \App\Models\Application::create(['reference_no' => 'APP-BROWSER-ROLES', 'user_id' => $account->id,
                        'program_id' => $subject->program_id, 'first_name' => 'Browser', 'last_name' => 'Applicant',
                        'email' => $account->email, 'academic_status' => 'new_student', 'status' => 'approved', 'reviewed_at' => now()]);
                }
            }
        }
        if ($journey !== 'admission') {
            $student = $this->makeUserWithRole('student', ['email' => 'browser-student@example.test', 'password' => Hash::make($password), 'status' => 'active']);
            $alternative = $section->replicate();
            $alternative->fill(['timetable_draft_id' => null, 'section_code' => 'BROWSER 1B', 'day_pattern' => 'TTH', 'capacity' => 5])->save();
            if ($journey === 'hold') {
                $occupant = Enrollment::factory()->create(['user_id' => $this->makeUserWithRole('student')->id,
                    'program_id' => $subject->program_id, 'subject_ids' => [$subject->id], 'status' => 'approved',
                    'school_year' => '2026-2027', 'semester' => 1]);
                $occupant->subjectOfferings()->create(['curriculum_subject_id' => $subject->id, 'class_schedule_id' => $section->id]);
            } else {
                $enrollment = Enrollment::factory()->pending()->create(['user_id' => $student->id,
                    'program_id' => $subject->program_id, 'subject_ids' => [$subject->id], 'year_level' => 1,
                    'school_year' => '2026-2027', 'semester' => 1, 'is_paid' => in_array($journey, ['replacement', 'refund']),
                    'requires_section_assignment' => false]);
                $enrollment->subjectOfferings()->create(['curriculum_subject_id' => $subject->id, 'class_schedule_id' => $section->id]);
                if ($enrollment->is_paid) {
                    $paymentBefore = Payment::factory()->create(['enrollment_id' => $enrollment->id])->fresh()->getRawOriginal();
                }
            }
        }
        $directory = storage_path('framework/testing/browser-flow-'.bin2hex(random_bytes(6)));
        mkdir($directory.'/sessions', 0777, true);
        mkdir($directory.'/uploads', 0777, true);
        $listener = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($listener, false);
        fclose($listener);
        $baseUrl = 'http://'.$address;
        $environment = array_merge(getenv(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'dtc_testing',
            'SESSION_DRIVER' => 'file', 'SESSION_SECURE_COOKIE' => 'false', 'MAIL_MAILER' => 'array',
            'CACHE_DRIVER' => 'array', 'APP_URL' => $baseUrl, 'BROWSER_SESSION_PATH' => $directory.'/sessions',
            'BROWSER_BASE_URL' => $baseUrl, 'BROWSER_PASSWORD' => $password, 'BROWSER_PROGRAM_ID' => (string) $subject->program_id,
            'ADMISSION_URL' => $baseUrl.'/apply',
            'BROWSER_REGISTRAR_EMAIL' => $registrar->email, 'BROWSER_CASHIER_EMAIL' => $cashier->email,
            'BROWSER_OUTPUT' => $directory, 'BROWSER_PHP' => PHP_BINARY, 'BROWSER_JOURNEY' => $journey,
            'BROWSER_SUBJECT_ID' => (string) $subject->id, 'BROWSER_ORIGINAL_SECTION' => (string) $section->id,
            'BROWSER_ALTERNATIVE_SECTION' => isset($alternative) ? (string) $alternative->id : '',
            'BROWSER_UPLOAD_PATH' => $directory.'/uploads']);
        $process = proc_open([PHP_BINARY, '-S', $address, '-t', public_path(), base_path('tests/Support/browser-testing-router.php')],
            [0 => ['pipe', 'r'], 1 => ['file', $directory.'/server.out', 'w'], 2 => ['file', $directory.'/server.err', 'w']], $pipes, base_path(), $environment);
        $this->assertIsResource($process);
        fclose($pipes[0]);
        try {
            $script = match ($journey) {
                'admission' => 'admission-enrollment.cjs',
                'roles' => 'role-pages.cjs',
                'layout' => 'admission-layout.cjs',
                default => 'enrollment-journeys.cjs',
            };
            $browser = proc_open(['node', base_path('tests/browser/'.$script)],
                [0 => ['pipe', 'r'], 1 => ['file', $directory.'/browser.out', 'w'], 2 => ['file', $directory.'/browser.err', 'w']], $browserPipes, base_path(), $environment);
            $this->assertIsResource($browser);
            fclose($browserPipes[0]);
            $deadline = microtime(true) + 330;
            do {
                $status = proc_get_status($browser);
                if (! $status['running']) {
                    break;
                }
                usleep(100000);
            } while (microtime(true) < $deadline);
            if ($status['running']) {
                proc_terminate($browser);
            }
            proc_close($browser);
            $this->assertFalse($status['running'], 'Browser flow timed out: '.$directory);
            $this->assertSame(0, $status['exitcode'], file_get_contents($directory.'/browser.out')."\n".file_get_contents($directory.'/browser.err')."\nArtifacts: ".$directory);
            if (in_array($journey, ['roles', 'layout'], true)) {
                return;
            }
            $email = $journey === 'admission' ? 'browser-applicant@example.test' : 'browser-student@example.test';
            $enrollment = Enrollment::whereHas('user', fn ($query) => $query->where('email', $email))->firstOrFail();
            $this->assertSame($journey === 'refund' ? 'rejected' : ($journey === 'hold' ? 'pending' : 'approved'), $enrollment->status);
            $this->assertSame($journey !== 'hold', $enrollment->is_paid);
            $this->assertFalse($enrollment->requires_section_assignment);
            $this->assertSame(1, $enrollment->subjectOfferings()->count());
            $this->assertSame($journey === 'hold' ? 0 : 1, Payment::where('enrollment_id', $enrollment->id)->where('status', 'verified')->count());
            $this->assertTrue($enrollment->user->hasRole('student'));
            if ($journey === 'replacement') {
                $this->assertSame($paymentBefore, $enrollment->payments()->firstOrFail()->getRawOriginal());
                $this->assertSame('approved', $enrollment->replacementRequests()->firstOrFail()->status);
            }
            if ($journey === 'refund') {
                $payment = $enrollment->payments()->firstOrFail();
                $this->assertSame('refunded', $payment->refund_status);
                $this->assertSame('500.00', $payment->refund_amount);
                $this->assertSame($cashier->id, $payment->refunded_by);
                foreach (['receipt_no', 'amount', 'paid_at', 'status', 'verified_at', 'verified_by'] as $field) {
                    $this->assertSame($paymentBefore[$field], $payment->getRawOriginal($field));
                }
            }
            if ($journey === 'gcash') {
                $this->assertSame(2, $enrollment->payments()->count());
                $this->assertSame(1, $enrollment->payments()->where('status', 'rejected')->count());
            }
            if ($journey === 'hold') {
                $this->assertDatabaseMissing('student_profiles', ['user_id' => $enrollment->user_id]);
                $this->assertSame($alternative->id, $enrollment->subjectOfferings()->firstOrFail()->class_schedule_id);
            }
        } finally {
            proc_terminate($process);
            proc_close($process);
        }
    }
}
