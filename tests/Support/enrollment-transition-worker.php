<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || Illuminate\Support\Facades\DB::selectOne('SELECT DATABASE() AS name')->name !== 'dtc_testing') {
    throw new RuntimeException('Transition workers require dtc_testing.');
}
[$script, $jobFile, $ready, $result] = $argv;
$job = json_decode(file_get_contents($jobFile), true, 512, JSON_THROW_ON_ERROR);
Illuminate\Support\Facades\Auth::loginUsingId($job['actor']);
$enrollment = App\Models\Enrollment::findOrFail($job['enrollment']);
$request = Illuminate\Http\Request::create('/test-transition', 'POST', $job['payload']);
$request->setLaravelSession(app('session')->driver());
$request->setUserResolver(fn () => Illuminate\Support\Facades\Auth::user());
$app->instance('request', $request);
file_put_contents($ready, (string) Illuminate\Support\Facades\DB::selectOne('SELECT CONNECTION_ID() AS id')->id);
try {
    $registrar = app(App\Http\Controllers\Registrar\EnrollmentController::class);
    if ($job['action'] === 'approve') {
        $registrar->approve($request, $enrollment, app(App\Services\EnrollmentSubjectReplacementService::class));
    } elseif ($job['action'] === 'recover') {
        $registrar->returnForSubjectReplacement($request, $enrollment);
    } elseif ($job['action'] === 'refund') {
        app(App\Http\Controllers\Cashier\PaymentController::class)->confirmRefund($request, $enrollment->payments()->firstOrFail());
    } elseif ($job['action'] === 'create') {
        app(App\Http\Controllers\Student\EnrollmentController::class)->store($request);
        if ($request->session()->has('error')) {
            throw Illuminate\Validation\ValidationException::withMessages(['enrollment' => $request->session()->get('error')]);
        }
    } else {
        throw new RuntimeException('Unknown transition.');
    }
    file_put_contents($result, json_encode(['outcome' => 'committed']));
} catch (Illuminate\Validation\ValidationException $exception) {
    file_put_contents($result, json_encode(['outcome' => 'validation', 'errors' => $exception->errors()]));
} catch (Throwable $exception) {
    file_put_contents($result, json_encode(['outcome' => 'exception', 'class' => get_class($exception), 'message' => $exception->getMessage()]));
    exit(1);
}
