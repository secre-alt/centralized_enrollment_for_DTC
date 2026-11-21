<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || Illuminate\Support\Facades\DB::selectOne('SELECT DATABASE() AS name')->name !== 'dtc_testing') {
    throw new RuntimeException('Browser state helpers require dtc_testing.');
}

// Read test state without adding application-only inspection or login routes.
$application = App\Models\Application::where('email', $argv[1])->first();
$user = $application?->user ?? App\Models\User::where('email', $argv[1])->first();
$enrollment = $user ? App\Models\Enrollment::where('user_id', $user->id)->latest('id')->first() : null;
$state = ['application_id' => $application?->id, 'application_status' => $application?->status,
    'enrollment_id' => $enrollment?->id, 'enrollment_status' => $enrollment?->status,
    'paid' => $enrollment?->is_paid, 'hold' => $enrollment?->requires_section_assignment,
    'payments' => $enrollment ? $enrollment->payments()->orderBy('id')->get()->map(fn ($payment) => $payment->getRawOriginal())->all() : [],
    'assignments' => $enrollment ? $enrollment->subjectOfferings()->orderBy('id')->pluck('class_schedule_id')->all() : [],
    'revision_status' => $enrollment?->replacementRequests()->latest('id')->value('status')];
if (($argv[2] ?? '') === 'activation' && $user && $application->status === 'approved') {
    // Emails are deliberately not delivered from this test server. Exercise the
    // normal setup page using a test-only broker token for the approved account.
    $token = Illuminate\Support\Facades\Password::broker('users')->createToken($user);
    $state['activation_path'] = '/set-password/'.$token.'?email='.urlencode($user->email);
}
echo json_encode($state, JSON_THROW_ON_ERROR);
