<?php

// Only used by the isolated PHPUnit browser harness, never by the app server.
if (PHP_SAPI !== 'cli-server' || getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'dtc_testing') {
    http_response_code(503);
    exit('The browser harness requires dtc_testing.');
}

$file = realpath(__DIR__.'/../../public'.parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$public = realpath(__DIR__.'/../../public');
if ($file && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file)) {
    return false;
}

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
if (Illuminate\Support\Facades\DB::selectOne('SELECT DATABASE() AS name')->name !== 'dtc_testing') {
    http_response_code(503);
    exit('Unsafe browser database configuration.');
}
// Exercise real HTTP security middleware only after confirming database isolation.
$app->detectEnvironment(fn () => 'production');
$app['config']->set('app.env', 'production');
$app['config']->set('app.debug', false);
$app['config']->set('session.files', getenv('BROWSER_SESSION_PATH'));
$app['config']->set('filesystems.disks.local.root', getenv('BROWSER_UPLOAD_PATH'));
$request = Illuminate\Http\Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
