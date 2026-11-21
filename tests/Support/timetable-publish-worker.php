<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || Illuminate\Support\Facades\DB::selectOne('SELECT DATABASE() AS name')->name !== 'dtc_testing') {
    throw new RuntimeException('The timetable worker requires dtc_testing.');
}
[$script, $draftId, $actorId, $ready, $result] = $argv;
Illuminate\Support\Facades\Auth::loginUsingId($actorId);
$request = Illuminate\Http\Request::create('/admin/timetables/'.$draftId, 'PUT', ['action' => 'publish', 'reviewed' => 1]);
$request->setLaravelSession(app('session')->driver());
$request->setUserResolver(fn () => Illuminate\Support\Facades\Auth::user());
$app->instance('request', $request);
Illuminate\Support\Facades\DB::beginTransaction();
// Establish an earlier repeatable-read snapshot before the scheduling lock.
Illuminate\Support\Facades\DB::table('class_schedules')->count();
$draft = App\Models\TimetableDraft::findOrFail($draftId);
file_put_contents($ready, (string) Illuminate\Support\Facades\DB::selectOne('SELECT CONNECTION_ID() AS id')->id);
try {
    app(App\Http\Controllers\Admin\TimetableController::class)->update($request, $draft, app(App\Services\TimetableService::class));
    Illuminate\Support\Facades\DB::commit();
    file_put_contents($result, 'published');
} catch (Illuminate\Validation\ValidationException $exception) {
    Illuminate\Support\Facades\DB::rollBack();
    file_put_contents($result, json_encode(['errors' => $exception->errors()]));
} catch (Throwable $exception) {
    Illuminate\Support\Facades\DB::rollBack();
    file_put_contents($result, json_encode(['exception' => get_class($exception), 'message' => $exception->getMessage()]));
    exit(1);
}
