<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || Illuminate\Support\Facades\DB::selectOne('SELECT DATABASE() AS name')->name !== 'dtc_testing') {
    throw new RuntimeException('Automatic assignment workers require dtc_testing.');
}
[$script, $userId, $subjectId, $ready, $result] = $argv;
Illuminate\Support\Facades\Auth::loginUsingId($userId);
$subject = App\Models\CourseSubject::findOrFail($subjectId);
$request = Illuminate\Http\Request::create('/portal/enrollment', 'POST', [
    'program_id' => $subject->program_id, 'year_level' => 1, 'semester' => 1, 'subject_ids' => [$subject->id],
]);
$request->setLaravelSession(app('session')->driver());
$request->setUserResolver(fn () => Illuminate\Support\Facades\Auth::user());
$app->instance('request', $request);
// Establish a snapshot INSIDE each real controller transaction. An extra outer
// test transaction would prevent Laravel from retrying an insert deadlock.
$snapshotTaken = false;
Illuminate\Support\Facades\Event::listen(Illuminate\Database\Events\TransactionBeginning::class, function () use (&$snapshotTaken) {
    $snapshotTaken = false;
});
Illuminate\Support\Facades\DB::listen(function ($query) use (&$snapshotTaken) {
    if (! $snapshotTaken && Illuminate\Support\Facades\DB::transactionLevel() > 0
        && str_contains($query->sql, '`users`') && str_contains(strtolower($query->sql), 'for update')) {
        $snapshotTaken = true;
        Illuminate\Support\Facades\DB::table('enrollment_subject_offerings')->count();
    }
});
file_put_contents($ready, (string) Illuminate\Support\Facades\DB::selectOne('SELECT CONNECTION_ID() AS id')->id);
try {
    app(App\Http\Controllers\Student\EnrollmentController::class)->store($request);
    $enrollment = App\Models\Enrollment::where('user_id', $userId)->where('school_year', '2026-2027')
        ->where('semester', 1)->where('status', 'pending')->latest('id')->firstOrFail();
    file_put_contents($result, $enrollment->requires_section_assignment ? 'held' : 'assigned');
} catch (Throwable $exception) {
    file_put_contents($result, json_encode(['exception' => get_class($exception), 'message' => $exception->getMessage()]));
    exit(1);
}
