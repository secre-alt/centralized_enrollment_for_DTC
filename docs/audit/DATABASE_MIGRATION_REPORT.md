# Database migration verification

November 27, 2026. Operational database was not migrated or reset.

## Safety evidence

- PHPUnit forces MySQL host/port/database/credentials/socket/URL settings; database is `dtc_testing`.
- The operational database name differs from `dtc_testing`; no cached Laravel configuration was present.
- A direct read-only test connection returned `SELECT DATABASE() = dtc_testing`.
- A read-only `SELECT VERSION()` returned `10.4.32-MariaDB` for this test connection. PDO's MySQL driver does not establish that the server is Oracle MySQL.
- Existing concurrency workers and migration tests independently assert the test database before destructive fixtures.
- Database test suites must remain sequential. Worker concurrency inside a test is intentional.

## Baseline schema and tests

There are 67 migration files. The Laravel 9 baseline passed 766 tests / 9,334 assertions, including existing fresh-schema and synthetic historical-preservation tests. This does not establish target-framework migration acceptance.

Upgrade-sensitive calls found:

- `2026_08_19_211805_make_receipt_no_nullable_on_payments_table.php`: nullable string change for receipt numbers.
- `2026_10_20_000002_create_academic_record_management_tables.php`: nullable/required unsigned user ID changes with explicit foreign-key handling.

Review declared column attributes and indexes at the Laravel 11 checkpoint: omitted modifiers on `change()` no longer carry forward automatically. Do not infer a desired schema from a fresh install alone or introduce ambiguous backfills.

Existing `AppointmentLifecycleMigrationTest` and `DocumentWorkflowSchemaTest` create synthetic historical records and compare preserved fields around additive migrations. They complement fresh-schema tests but do not constitute a full representative database upgrade rehearsal.

## Pending acceptance

An official MariaDB 11.4.14 Windows archive was downloaded and matched its published SHA-256 (`9d9b410eb7b65f6616f29d933d76ee90cf7af4ba179a2d437e0f09f5bf6650df`). Windows Application Control rejected `mariadbd.exe --no-defaults --version`. No server was initialized or started, no Windows service was installed, and the existing XAMPP server was unchanged. Maintained-server acceptance therefore remains unverified on this host; it requires an approved runtime/host. The policy was not bypassed.

`tests/Support/framework-upgrade-snapshot.php` supplies the between-checkpoint rehearsal. It reads PHPUnit's test environment before bootstrapping, refuses cached configuration or a database other than MySQL `dtc_testing`, and creates synthetic owner/role/program/subject/enrollment/verified-payment/appointment/document records in a transaction. It records all table row counts/hashes and column/index/foreign-key metadata, without exporting record contents or credentials.

Run only while no database suite is active, using the explicit patched PHP executable:

```text
php tests/Support/framework-upgrade-snapshot.php capture
php tests/Support/framework-upgrade-snapshot.php verify
# Install the next dependency checkpoint without migrating the database.
php tests/Support/framework-upgrade-snapshot.php verify
# Run the complete checkpoint suite, which intentionally rebuilds dtc_testing.
```

Archive each capture before the next one. Compare the archived schema metadata with the next fresh-schema capture separately from record hashes: a full test suite intentionally replaces synthetic data. The snapshot is stored under ignored `storage/framework/testing/`; verification must not substitute for role, eligibility, state-transition, payment or concurrency tests.

Actual Laravel 10 capture and immediate verification passed on PHP 8.4.28 across 49 tables. The suite's teardown had removed its disposable schema; the helper now runs ordinary pending migrations only after its actual database guard, before creating synthetic data. No reset/drop command was added. Post-install verification on Laravel 11.57.2 matched all schema metadata and stored record hashes exactly. The archived snapshot is `storage/logs/modernization-checkpoint10-snapshot.json`. Laravel 11 fresh-schema comparison remains pending until its complete suite finishes.

The tool rejects invalid modes before application bootstrap; this guard and PHP syntax were checked. An old Pint 1.5 formatting check passes but emits PHP 8.4 deprecations from its bundled dependencies; update the existing formatter at the next compatible dependency checkpoint rather than suppressing warnings.

Target-framework fresh installation, representative existing-data upgrade, exact financial/receipt/appointment preservation, foreign keys/indexes/decimal precision, and MySQL lock tests must be recorded at their actual checkpoint. No new migration is currently proposed. No production migration, rollback, backup restore or data backfill has been executed.
