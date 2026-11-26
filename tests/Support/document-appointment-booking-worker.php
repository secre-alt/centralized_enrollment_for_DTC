<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || Illuminate\Support\Facades\DB::selectOne('SELECT DATABASE() AS name')->name !== 'dtc_testing') {
    throw new RuntimeException('The concurrency worker requires dtc_testing.');
}
[$script, $userId, $slotId, $ready, $result] = $argv;

$user = App\Models\User::findOrFail($userId);
Illuminate\Support\Facades\Auth::login($user);
$request = Illuminate\Http\Request::create('/portal/appointments', 'POST');
$request->setUserResolver(fn () => $user);
$app->instance('request', $request);
file_put_contents($ready, (string) Illuminate\Support\Facades\DB::selectOne('SELECT CONNECTION_ID() AS id')->id);

try {
    app(App\Services\DocumentAppointmentBookingService::class)->book(
        $user, (int) $slotId, [['document_type' => 'tor', 'copies' => 1]],
        'transcript', 'Concurrency test', 'walk_in', null, null, $request
    );
    file_put_contents($result, 'booked');
} catch (Throwable $exception) {
    file_put_contents($result, json_encode(['exception' => get_class($exception), 'message' => $exception->getMessage()]));
}
