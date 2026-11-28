# Framework upgrade execution report

November 27, 2026 (Asia/Manila). Work is isolated on `upgrade/laravel-13-controlled`.

## Completed baseline

Laravel 9.52.21: 766 tests / 9,334 assertions passed on local PHP 8.4.27. Baseline platform requirements failed on Nette schema 1.2.5. A compatible official PHP 8.2 runtime could not execute because of Windows Application Control; no policy bypass was attempted.

## Laravel 10 checkpoint

Installed Laravel 10.50.5 with targeted framework, authentication, Permission, Collision/Ignition, Nette and advisory-affected dependency updates. Composer package discovery completed. The existing application skeleton and AdminLTE 3.2 integration remain in place.

Command:

```text
composer update laravel/framework laravel/sanctum spatie/laravel-ignition nunomaduro/collision spatie/laravel-permission nette/schema guzzlehttp/guzzle guzzlehttp/psr7 league/commonmark league/flysystem "symfony/*" --with-all-dependencies --no-interaction --no-ansi
```

- `composer validate --strict --no-ansi`: passed.
- `composer check-platform-reqs --no-ansi`: passed on actual PHP 8.4.27.
- Package discovery and `php artisan --version`: application boots.
- Initial complete PHPUnit suite: 766 tests / 9,324 assertions, with two mail-fake setup errors. Laravel 10 requires a real manager when constructing its mail fake; tests had replaced it with an uninitialized mock. Both tests now restore the original fake after the intentional enqueue failure, retaining all assertions. Focused verification passed 2 tests / 29 assertions on PHP 8.4.28. The complete patched-runtime rerun passed 766 tests / 9,334 assertions (42:17.950, 146 MB); automated Laravel 10 checkpoint accepted.
- Install-time audit: 11 advisories across Laravel and DomPDF remain. Repeated audit retrieved cached metadata after a Packagist timeout; freshness must be retried.
- A subsequent network-enabled audit using isolated PHP 8.4.28 completed without the timeout warning and confirmed the 11 remaining advisories.
- No new migrations or institutional business-rule changes have been applied.

Composer reports an existing PSR-4 warning for the manually invoked PHP browser harness under lowercase `tests/browser`; that harness is outside the default Unit/Feature suite. It has not been removed or treated as covered by the default suite.

## Frontend remediation

Vite references only the existing `resources/js/app.js` entry; the nonexistent CSS entry was removed. Upgraded Vite to 7.3.8 and Laravel Vite plugin to 2.1.0, which declare compatible Node requirements satisfied by installed Node 24.21.0. No UI framework or application asset-loading migration was performed; `laravel_asset_bundling` remains disabled.

`npm.cmd install --no-fund` succeeded and reported zero vulnerabilities. `npm.cmd run build` passed: 64 modules transformed and generated a JavaScript asset/manifest. npm warned about esbuild's install-script policy; the build nevertheless succeeded without a policy bypass. Final explicit npm audit result must be recorded separately.

The explicit post-install `npm.cmd audit --json` also reports zero vulnerabilities. A hash-verified PHP 8.4.28 runtime now boots this checkpoint and passes platform requirements; it is used explicitly for later checkpoint validation. System PHP remains 8.4.27. See `UPGRADE_PLAN.md` for the optional ZIP-extension policy limitation.

## Remaining checkpoints

Laravel 11.57.2 passed its complete repeat (775 tests / 9,374 assertions). Laravel 12.74.0 passed 781 tests / 9,401 assertions. Laravel 13.44.1 is installed and passed 781 tests / 9,404 assertions, focused compatibility checks, Composer validation, platform checks, audit and final snapshots.

## Laravel 11 checkpoint

- Actual runtime: isolated, official hash-verified PHP 8.4.28. Composer now requires `~8.4.28`; the older global PHP executable does not satisfy this requirement.
- Updated Framework, Sanctum 4.4.0, Permission 6.25.0, Collision 8.9.6, PHPUnit 11.5.58 and Pint 1.32.3 using a targeted update with `--with-all-dependencies --minimal-changes`. Carbon remains 2.73.0 at this checkpoint.
- Removed the direct Doctrine DBAL requirement after confirming no application usage. Existing schema changes use Laravel's native implementation. Fresh-schema comparison remains required after the suite.
- Preserved kernels, providers, route groups and custom CSRF/cookie middleware. Updated Sanctum's middleware keys; the existing personal-access-token migration already includes expiry, so no duplicate migration was published.
- Converted 122 test annotations in 12 files to PHPUnit attributes and migrated PHPUnit XML. Forced `dtc_testing` configuration is retained. Discovery comparison confirms the same 766 test identities/datasets; named dataset output differences were normalized for comparison.
- Composer strict validation and actual platform checks pass. All 240 route method/URI/name/action/middleware entries match the baseline.
- Synthetic Laravel 10 capture and immediate verification passed across 49 tables. After Laravel 11 installation, complete schema metadata and all stored record hashes matched exactly.
- Complete repeat passed 775 tests / 9,374 assertions, 20:11.805, 140 MB, on PHP 8.4.28. This includes nine new security/data-preservation regressions. The initial 766-test run had one failure during a recorded OS clock jump; freezing that multi-page fixture preserved idle-session security and its focused rerun passed 25 assertions.
- Install-time advisories remain, and this intermediate version is not a deployment target. Fresh-schema comparison and the next transition's synthetic snapshot verification remain separate checks.

- Subsequent fresh-schema comparison passed: all 49 tables match the Laravel 10 schema metadata. A fresh checkpoint 11 synthetic capture and immediate verification also passed.
- Authenticated browser acceptance at this checkpoint passed 60 page/viewport checks across all six roles plus three expected portal-to-admin denials. The initial run timed out on a later login navigation; matching the existing harness's 60-second navigation allowance resolved it. This supplies representative pre-12/13 screenshots, not a pixel-identical Laravel 9 baseline or complete workflow acceptance.

## Laravel 12 checkpoint

- Targeted command: `composer update laravel/framework barryvdh/laravel-dompdf --with-all-dependencies --minimal-changes --no-interaction --no-ansi`, executed with PHP 8.4.28 and the forced testing environment for Composer scripts.
- Installed Framework 12.74.0, Carbon 3.15.1, DomPDF wrapper 3.1.2/engine 3.1.6 and required transitive packages. Other compatible locked packages were retained where possible. Native date/report callers were reviewed for signed/fractional differences; the complete suite remains the acceptance gate.
- Added the exact `Tests\\Browser\\` PSR-4 mapping for the existing lowercase directory; package discovery completes without its former warning. Existing kernels/providers/middleware and route groups remain in place.
- Strict Composer validation and actual platform requirements pass. Fresh `composer audit --locked --no-ansi --format=json` reports zero advisories and zero abandoned packages.
- All 240 route method/URI/name/action/middleware entries match baseline. The Laravel 11-to-12 transition preserved all schema metadata and stored record hashes across 49 tables before running destructive disposable fixtures.
- Adopted DomPDF 3's remote-resource restriction after confirming current PDF templates use local/inline content. A regression first detected the old published `enable_remote=true`; disabling it passes the runtime-options/local-PDF check (1 test / 3 assertions), while PHP execution remains disabled. No application-specific SSRF exploit is claimed.
- Complete suite passed 781 tests / 9,401 assertions in 1:12:09.381.

## Laravel 13 checkpoint

- Installed Framework 13.44.1, Tinker 3.2, Permission 7.4.2, Ignition 2.13.0 and PHPUnit 12.5.45 on isolated PHP 8.4.28.
- The 49-table schema and synthetic record hashes match the Laravel 12 checkpoint. Complete suite passed 781 tests / 9,404 assertions in 27:34.345; focused compatibility checks passed 22 tests / 98 assertions.
- The role browser harness passed 60 page/viewport checks. The admission layout harness still reports 35px horizontal overflow in the existing fixture and remains an open UI gate.
