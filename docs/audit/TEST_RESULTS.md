# Modernization test results

November 27, 2026 (Asia/Manila). Commands run from the repository root. Database suites use MySQL `dtc_testing` sequentially; no production migration command was run.

| Stage | Command | Actual result |
| --- | --- | --- |
| Baseline | `php -v` | PHP 8.4.27 CLI; Composer uses the same executable |
| Baseline | `php artisan --version` | Laravel 9.52.21 |
| Baseline | `composer validate --strict --no-ansi` | Manifest valid, strict warning for exact Permission 5.10 constraint |
| Baseline | `composer check-platform-reqs --no-ansi` | Failed: Nette schema 1.2.5 excludes PHP 8.4 |
| Baseline | `php vendor/phpunit/phpunit/phpunit --no-coverage` | PASS: 766 tests, 9,334 assertions; 18:22.768; 142 MB |
| Baseline | `php vendor/phpunit/phpunit/phpunit --list-tests-xml storage/logs/modernization-tests-baseline.xml` | Discovery inventory saved before upgrading tooling |
| Baseline | `php artisan route:list --json` | 240 routes; inventory saved |
| Baseline | `composer audit --locked --no-ansi --format=json` | 59 advisories / 13 packages; network retry succeeded; see security report |
| Baseline | `npm audit --json` | 3 affected packages: 1 High, 1 Moderate, 1 Low |
| Baseline | `npm.cmd run build` | Sandbox esbuild parent-directory access failure; elevated retry reached Vite and failed on missing resources/css/app.css |
| Baseline | `node tests/browser/enrollment-revision.cjs` | Sandbox launch failure; elevated retry PASS |
| Baseline | Official isolated PHP 8.2.36 `php.exe -v` | Windows Application Control rejected execution after archive hash verification |
| Runtime preparation | Official isolated PHP 8.4.28 `php.exe -v`, `-m`, Composer `--version`, platform check and Artisan version | Passed after correcting temporary INI formatting; required extensions load; optional ZIP DLL blocked and not configured |
| Laravel 10 | `composer validate --strict --no-ansi` | PASS |
| Laravel 10 | `composer check-platform-reqs --no-ansi` | PASS on PHP 8.4.27 and separately on isolated PHP 8.4.28 |
| Laravel 10 | `php artisan --version` | Laravel 10.50.5 |
| Laravel 10 | `php artisan route:list --json` | 240 routes; method/URI/name sets identical to baseline |
| Frontend remediation | `npm.cmd install --no-fund`, `npm.cmd run build`, `npm.cmd audit --json` | PASS; build transforms 64 modules; explicit audit reports zero vulnerabilities |
| Laravel 10 | Patched PHP + Composer `audit --locked --no-ansi --format=json` | Fresh network retry completed with 11 advisories across Laravel and DomPDF; nonzero finding exit, not a clean audit |
| UI preservation | `node tests/browser/timetables.cjs` | PASS: six widths, light/dark layout and action spacing |
| UI baseline finding | `node tests/browser/portal-modals.cjs` before fixture correction | FAIL: welcome dialog renders 16px; generic fixture expected 12px. Styles and fixture matched original commit; unrelated to framework upgrade |
| UI preservation | `node --check tests/browser/portal-modals.cjs`, then Edge fixture after user-selected expectation correction | PASS: 42 fixtures, seven dialog families, two themes, three widths; welcome remains 16px and other dialogs remain checked at 12px |
| Laravel 10 initial complete suite | `php vendor/phpunit/phpunit/phpunit --no-coverage --debug` | 766 tests / 9,324 assertions; 2 errors in tests replacing a mail fake with a mock and then constructing another fake; 22:21.133, 146 MB |
| Laravel 10 mail correction | Isolated PHP 8.4.28 + PHPUnit filter for the two affected mail retry tests | PASS: 2 tests / 29 assertions; original mail fake restored after the deliberate enqueue failure; all retry assertions retained |
| Laravel 10 complete repeat | Isolated PHP 8.4.28 + PHPUnit `--no-coverage --debug` | PASS: 766 tests / 9,334 assertions; 42:17.950; 146 MB |
| Existing-data rehearsal | Guarded snapshot `capture`, `verify`, install Laravel 11, then `verify` | PASS: all schema metadata and record hashes across 49 tables preserved |
| Laravel 11 | Composer strict validation and actual platform checks | PASS on PHP 8.4.28 |
| Laravel 11 | PHPUnit configuration migration and test discovery comparison | PASS: same 766 test identities/datasets; forced MySQL testing environment preserved |
| Laravel 11 | Route inventory comparison | PASS: same 240 method/URI/name/action/middleware entries |
| Laravel 11 initial complete suite | Patched PHP + PHPUnit `--no-coverage --debug` | 766 tests executed, exit 1; one session-expiry failure during a recorded Windows clock correction advancing time by approximately 2h47m |
| Laravel 11 clock-fixture correction | Focused automatic-section workflow with frozen test time | PASS: 1 test / 25 assertions; application idle-session enforcement unchanged |
| Security regressions | `ModernizationSecurityTest` | PASS: 9 tests / 40 assertions; malformed search, environment-restricted development administrator, account/receipt/actor preservation, JSON curriculum history, credited subjects and unused-record deletion |
| Laravel 11 complete repeat | Patched PHP + PHPUnit `--no-coverage --log-junit storage/logs/modernization-laravel11-results.xml` | PASS: 775 tests / 9,374 assertions; 20:11.805; 140 MB. Includes the nine new security regressions |
| Expanded JavaScript initial run | `node --test tests/js/*.test.cjs tests/js/*.test.js` | 82 passed / 8 failed: admission rules subprocess used global PHP 8.4.27; seven stale fixture failures involved missing DOM listener mocks or untranslated Blade directives |
| Expanded JavaScript correction | Patched PHP directory prepended to process PATH; PHPUnit environment applied; same Node command | PASS: 94 tests, zero failures. Three fixture files corrected; application JavaScript/CSS unchanged. The earlier failed admission file had counted as one file failure rather than its five discovered tests |
| Standalone admission layout | `node tests/browser/admission-layout.cjs` | Failed to initialize against the script's default server URL; stopped batch. Requires explicit isolated staging URL before retry; no application submitted |
| Additional input regressions | `ModernizationInputTest` before fixes | Five failures reproduced malformed password-page/hash inputs and incorrect enrollment guidance |
| Input/history/help repeat | PHPUnit filter `ModernizationInputTest\|ModernizationSecurityTest\|HelpCenterTest` | PASS: 18 tests / 142 assertions; includes unchanged-password checks after rejected malformed requests |
| PHP 8.4 CSV compatibility | PHPUnit filter `TimetableDraftTest\|AcademicRecordManagementTest` | PASS: 40 tests / 432 assertions; CSV escape arguments explicitly preserve the previous backslash behavior |
| Laravel 11 six-role browser initial run | Explicit PHP browser harness, `@roles` dataset | Completed 40 page/viewport checks, then a 15-second navigation timeout loading the next login page; no uncaught JavaScript errors in completed checks |
| Laravel 11 six-role browser repeat | Same harness with the existing journey scripts' 60-second navigation allowance | PASS: 1 PHP test / 9 assertions; Node verifies 60 page/viewport checks across all six roles, 60 screenshots, three expected portal-to-admin 403 denials, zero uncaught JS errors and zero unexpected failed requests. The three browser console errors correspond exactly to the intentional 403 denials; cancelled polls are recorded separately |
| Laravel 11 fresh schema | Compare archived checkpoint 10/11 snapshot schema metadata | PASS: all 49 tables match; capture and immediate record-hash verification pass |
| Laravel 12 install/platform | Targeted Composer update, strict validation, actual platform check | PASS on PHP 8.4.28; Framework 12.74.0 / Carbon 3.15.1 / DomPDF 3.1.6 installed |
| Laravel 12 dependency audit | Fresh network-enabled `composer audit --locked --no-ansi --format=json` | PASS: zero advisories, zero abandoned packages |
| Laravel 11-to-12 existing records | Guarded snapshot verification before the suite | PASS: complete schema metadata and all stored record hashes preserved across 49 tables |
| Laravel 12 routes | Compare route JSON against original inventory | PASS: all 240 entries and declared middleware unchanged |
| DomPDF resource restriction | New runtime-options/local-render regression, red then green | Old published configuration failed remote-access assertion; corrected configuration passes 1 test / 3 assertions |
| Laravel 12 complete suite | Patched PHP + PHPUnit `--no-coverage --log-junit storage/logs/modernization-laravel12-results.xml` | PASS: 781 tests / 9,401 assertions; 1:12:09.381 |
| Laravel 13 install/platform/audit | Composer update, strict validation, platform check and audit | PASS: Framework 13.44.1, Permission 7.4.2, PHPUnit 12.5.45, zero advisories |
| Laravel 13 complete suite | Patched PHP + PHPUnit `--no-coverage --log-junit storage/logs/modernization-laravel13-results.xml` | PASS: 781 tests / 9,404 assertions; 27:34.345 |
| Laravel 13 focused compatibility | Workflow mail, security, input and PDF resource checks | PASS: 22 tests / 98 assertions |
| Laravel 13 JavaScript | `node --test tests/js/*.test.cjs` with patched PHP first on PATH | PASS: 93 tests / 93 passed |
| Laravel 13 role browser repeat | Explicit `@roles` dataset | PASS: 1 PHP test / 9 assertions; 60 page/viewport checks |
| Laravel 13 admission layout | Explicit `@layout` dataset | FAIL: existing fixture reports 35px horizontal overflow; no application CSS change made |

The Laravel 10 initial errors were upgrade-related test setup incompatibilities, not pre-existing baseline failures. The complete repeat on PHP 8.4.28 passed: 766 tests, 9,334 assertions, 42:17.950, 146 MB. This matches the baseline test and assertion counts. The focused pass alone was not used as full checkpoint acceptance.

The baseline establishes observed behavior under the installed PHP 8.4 runtime, not official Laravel 9/PHP 8.4 support. Test success does not waive the failed platform check or dependency advisories. There were no PHPUnit baseline failures to subtract from later failures.

The Edge fixture checks revision review, confirmation reset and escaped text rendering. It is not authenticated acceptance for every role or a before/after screenshot comparison.

Checkpoint installations, complete suites, final audits/build, browser role acceptance and staging/rollback checks will be recorded only after execution. Logs under `storage/logs/modernization-*` are local diagnostic artifacts; selected safe baseline evidence is retained under `docs/audit/evidence/`.
