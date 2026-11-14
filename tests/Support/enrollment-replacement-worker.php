<?php

// Isolated subprocess used only against the dedicated PHPUnit database.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || Illuminate\Support\Facades\DB::selectOne('SELECT DATABASE() AS name')->name !== 'dtc_testing') {
    throw new RuntimeException('The concurrency worker requires dtc_testing.');
}
[$script, $enrollmentId, $itemId, $offeringId, $ready, $result] = $argv;
$enrollment = App\Models\Enrollment::findOrFail($enrollmentId);
Illuminate\Support\Facades\Auth::loginUsingId($enrollment->user_id);
$request = Illuminate\Http\Request::create('/portal/enrollment/'.$enrollmentId.'/revise', 'POST', [
    'confirm_revision' => '1', 'replacements' => [$itemId => $offeringId],
]);
$request->setLaravelSession(app('session')->driver());
$request->setUserResolver(fn () => Illuminate\Support\Facades\Auth::user());
$app->instance('request', $request);
file_put_contents($ready, (string) Illuminate\Support\Facades\DB::selectOne('SELECT CONNECTION_ID() AS id')->id);
try {
    app(App\Http\Controllers\Student\EnrollmentController::class)->submitReplacement(
        $request, $enrollment, app(App\Services\EnrollmentSubjectReplacementService::class)
    );
    file_put_contents($result, 'submitted');
} catch (Illuminate\Validation\ValidationException $exception) {
    file_put_contents($result, json_encode(['errors' => $exception->errors()]));
} catch (Throwable $exception) {
    file_put_contents($result, json_encode(['exception' => get_class($exception), 'message' => $exception->getMessage()]));
    exit(1);
}
