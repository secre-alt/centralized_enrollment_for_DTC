<?php

use App\Models\Appointment;
use App\Models\CourseSubject;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

$mode = $argv[1] ?? '';
if (! in_array($mode, ['capture', 'verify'], true)) {
    throw new InvalidArgumentException('Use capture or verify; run only between database suites.');
}

$root = dirname(__DIR__, 2);
$configuration = simplexml_load_file($root.'/phpunit.xml');
$environment = [];
foreach ($configuration->php->env as $variable) {
    $environment[(string) $variable['name']] = (string) $variable['value'];
}
if (($environment['DB_DATABASE'] ?? null) !== 'dtc_testing'
    || ($environment['DB_CONNECTION'] ?? null) !== 'mysql'
    || ($environment['APP_ENV'] ?? null) !== 'testing'
    || is_file($root.'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Requires uncached PHPUnit configuration for MySQL dtc_testing.');
}
// Apply the test configuration before application providers can resolve a database.
foreach ($environment as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $exception) {
    fwrite(STDERR, get_class($exception).': upgrade snapshot failed; no record values printed.'.PHP_EOL);
    exit(1);
});
if (! app()->environment('testing') || DB::selectOne('SELECT DATABASE() AS name')->name !== 'dtc_testing') {
    throw new RuntimeException('The upgrade snapshot requires dtc_testing.');
}
$path = storage_path('framework/testing/framework-upgrade-snapshot.json');
File::ensureDirectoryExists(dirname($path));

if ($mode === 'capture') {
    // Some database suites remove their disposable schema during teardown.
    if ($app->make(Kernel::class)->call('migrate', ['--force' => true]) !== 0) {
        throw new RuntimeException('Could not migrate the guarded testing database.');
    }
    DB::transaction(function () {
        $owner = User::factory()->create(['email' => 'upgrade-'.Str::uuid().'@example.test']);
        $owner->assignRole(Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']));
        $subject = CourseSubject::factory()->create();
        $enrollment = Enrollment::factory()->paid()->create([
            'user_id' => $owner->id, 'program_id' => $subject->program_id,
            'subject_ids' => [$subject->id], 'school_year' => '2026-2027',
        ]);
        Payment::factory()->create(['enrollment_id' => $enrollment->id, 'verified_at' => now()]);
        $appointment = Appointment::factory()->confirmed()->create(['user_id' => $owner->id]);
        DocumentRequest::create([
            'user_id' => $owner->id, 'appointment_id' => $appointment->id, 'document_type' => 'tor',
            'status' => 'ready', 'fee' => '50.00', 'is_paid' => true, 'payment_status' => 'verified',
            'amount_received' => '50.00', 'receipt_no' => 'UPGRADE-'.Str::uuid(),
            'verified_at' => now(),
        ]);
    });
}

$schema = [
    'tables' => DB::select("SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME"),
    'columns' => DB::select('SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLUMN_COMMENT, COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION'),
    'indexes' => DB::select('SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART, INDEX_TYPE, COLLATION FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX'),
    'foreign_keys' => DB::select('SELECT TABLE_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, UPDATE_RULE, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() ORDER BY TABLE_NAME, CONSTRAINT_NAME'),
    'foreign_key_columns' => DB::select('SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, ORDINAL_POSITION, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION'),
];
$columns = collect($schema['columns'])->keyBy(fn ($column) => $column->TABLE_NAME.'.'.$column->COLUMN_NAME);
if (($columns->get('payments.receipt_no')->IS_NULLABLE ?? null) !== 'YES'
    || ($columns->get('student_profiles.user_id')->IS_NULLABLE ?? null) !== 'YES') {
    throw new RuntimeException('Expected completed schema with nullable receipt and academic identity links.');
}
$records = [];
foreach ($schema['tables'] as $table) {
    $rows = DB::table($table->TABLE_NAME)->get()->map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR))->all();
    sort($rows, SORT_STRING);
    $records[$table->TABLE_NAME] = ['rows' => count($rows), 'sha256' => hash('sha256', implode("\n", $rows))];
}
// Store hashes, never the records or credentials, in the review artifact.
$snapshot = json_decode(json_encode(['schema' => $schema, 'records' => $records], JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
if ($mode === 'capture') {
    if (file_put_contents($path, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Could not write the upgrade snapshot.');
    }
} else {
    if (! is_file($path)) {
        throw new RuntimeException('Capture a snapshot before verifying the transition.');
    }
    $expected = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if ($expected !== $snapshot) {
        file_put_contents($path.'.actual', json_encode($snapshot, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL, LOCK_EX);
        throw new RuntimeException('Schema or stored records changed; inspect the snapshot and .actual artifact.');
    }
}
echo strtoupper($mode).': Laravel '.app()->version().', PHP '.PHP_VERSION.', '.count($records).' tables; schema and record hashes recorded or verified.'.PHP_EOL;
