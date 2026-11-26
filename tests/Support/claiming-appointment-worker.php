<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || Illuminate\Support\Facades\DB::selectOne('SELECT DATABASE() AS name')->name !== 'dtc_testing') {
    throw new RuntimeException('The claiming worker requires dtc_testing.');
}
[$script, $jobPath, $ready, $result] = $argv;
$job = json_decode(file_get_contents($jobPath), true, 512, JSON_THROW_ON_ERROR);
$user = App\Models\User::findOrFail($job['user']);
Illuminate\Support\Facades\Auth::login($user);
$request = Illuminate\Http\Request::create(($job['action'] ?? '') === 'ready' ? '/registrar/documents/'.$job['documents'][0].'/status' : '/portal/claiming-appointments', 'POST');
$request->merge(array_intersect_key($job, array_flip(['operation_key', 'lifecycle_version'])));
$request->merge(['operation_key' => $job['operation_key'] ?? (string) Illuminate\Support\Str::uuid()]);
$request->setUserResolver(fn () => $user);
$app->instance('request', $request);
if (! empty($job['snapshot'])) {
    Illuminate\Support\Facades\DB::beginTransaction();
    // Prove that locking reads remain correct after an earlier MySQL snapshot.
    Illuminate\Support\Facades\DB::table('appointments')->count();
}
file_put_contents($ready, (string) Illuminate\Support\Facades\DB::selectOne('SELECT CONNECTION_ID() AS id')->id);
try {
    $outcome = 'booked';
    if (($job['action'] ?? 'claim') === 'walkin') {
        $request->merge(['handover_confirmed' => '1', 'document_ids' => $job['documents'], 'claimant_id' => $job['claimant']]);
        app(App\Services\WalkInDocumentReleaseService::class)->release($request, $job['claimant'], $job['documents']);
        $outcome = 'released';
    } elseif (($job['action'] ?? 'claim') === 'ready') {
        $request->merge(['status' => 'ready']);
        app(App\Services\DocumentReadyService::class)->mark($request, App\Models\DocumentRequest::findOrFail($job['documents'][0]));
        $outcome = 'ready';
    } elseif (($job['action'] ?? 'claim') === 'confirm') {
        app(App\Http\Controllers\Registrar\AppointmentController::class)->confirm(App\Models\Appointment::findOrFail($job['appointment']));
        $outcome = 'confirmed';
    } elseif (($job['action'] ?? 'claim') === 'edit') {
        $request->merge(['appointment_slot_id' => $job['slot'], 'document_type' => 'transcript', 'purpose' => 'Concurrent edit']);
        app(App\Http\Controllers\Student\AppointmentController::class)->update($request, App\Models\Appointment::findOrFail($job['appointment']));
        $outcome = 'updated';
    } elseif (($job['action'] ?? 'claim') === 'legacy') {
        $request->merge(['appointment_slot_id' => $job['slot']]);
        app(App\Http\Controllers\Alumni\DocumentRequestController::class)->bookPickup($request, App\Models\DocumentRequest::findOrFail($job['documents'][0]));
    } elseif (($job['action'] ?? 'claim') === 'release') {
        $request->merge(['handover_confirmed' => '1', 'lifecycle_version' => $job['lifecycle_version'] ?? App\Models\Appointment::findOrFail($job['appointment'])->lifecycle_version]);
        app(App\Services\ClaimingAppointmentReleaseService::class)->release($user, $job['appointment'], $job['documents'], $request);
        $outcome = 'released';
    } elseif (($job['action'] ?? 'claim') === 'standalone') {
        $request->merge(['documents' => [['document_type' => 'diploma', 'copies' => 1]], 'payment_method' => 'walk_in']);
        app(App\Services\DocumentSubmissionService::class)->submit($request, $request->input('documents'));
        $outcome = 'submitted';
    } elseif (($job['action'] ?? 'claim') === 'submission') {
        app(App\Services\DocumentAppointmentBookingService::class)->book($user, $job['slot'],
            [['document_type' => 'tor', 'copies' => 1]], 'transcript', null, 'walk_in', null, null, $request);
    } else {
        app(App\Services\ClaimingAppointmentBookingService::class)->book($user, $job['slot'], $job['documents'], $request);
    }
    if (! empty($job['snapshot'])) Illuminate\Support\Facades\DB::commit();
    file_put_contents($result, $outcome);
} catch (Throwable $exception) {
    if (Illuminate\Support\Facades\DB::transactionLevel()) Illuminate\Support\Facades\DB::rollBack();
    file_put_contents($result, json_encode(['exception' => get_class($exception), 'message' => $exception->getMessage()]));
}
